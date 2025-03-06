<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Regexp, Syntax};
use Texy\Nodes\{DefinitionListNode, FigureNode, ListItemNode, ListNode, ListType, ParagraphNode};
use Texy\Output\Html;
use Texy\Parsing\ParseContext;
use function count, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	/** @var array<string, array{string, ListType, 2?: string}> [regex, type, next-regex?] */
	public array $bullets = [
		'*' => ['\* [ \t]', ListType::Unordered],
		'-' => ['[\x{2013}-] (?! [>-] )', ListType::Unordered],
		'+' => ['\+ [ \t]', ListType::Unordered],
		'1.' => ['1 \. [ \t]' /* not \d! */, ListType::Decimal, '\d{1,3} \. [ \t]'],
		'1)' => ['\d{1,3} \) [ \t]', ListType::Decimal],
		'I.' => ['I \. [ \t]', ListType::UpperRoman, '[IVX]{1,4} \. [ \t]'],
		'I)' => ['[IVX]+ \) [ \t]', ListType::UpperRoman], // before A) !
		'a)' => ['[a-z] \) [ \t]', ListType::LowerAlpha],
		'A)' => ['[A-Z] \) [ \t]', ListType::UpperAlpha],
	];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlOutput->addRenderer($this->renderList(...));
		$texy->htmlOutput->addRenderer($this->renderItem(...));
		$texy->htmlOutput->addRenderer($this->renderDefinitionList(...));
	}


	public function beforeParse(): void
	{
		$re = $reUl = [];
		foreach ($this->bullets as $desc) {
			$re[] = $desc[0];
			if (!$desc[1]->isOrdered()) {
				$reUl[] = $desc[0];
			}
		}

		$this->texy->addBlock(
			Syntax::List,
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $re) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mUx',
			$this->parseList(...),
		);

		$this->texy->addBlock(
			Syntax::DefinitionList,
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)?   # modifier (1)
				( \S .{0,2000} )                      # definition term (2)
				: [ \t]*                              # colon separator
				' . Patterns::MODIFIER_H . '?         # modifier (3)
				\n
				([ \t]++)                             # indentation (4)
				(' . implode('|', $reUl) . ')         # description marker (5)
				[ \t]*+
				\S .*                                 # content
			$~mUx',
			$this->parseDefList(...),
		);
	}


	/**
	 * Parses list.
	 * @param  array{string, ?string, string}  $matches
	 */
	public function parseList(ParseContext $context, array $matches): ?ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$bullet = null;
		$min = 1;
		$start = null;
		$listType = ListType::Unordered;

		foreach ($this->bullets as $key => $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~Ax')) {
				$bullet = $desc[2] ?? $desc[0];
				$min = isset($desc[2]) ? 2 : 1;
				$listType = $desc[1];
				if ($listType->isOrdered()) {
					if ($key[0] === '1' && (int) $mBullet > 1) {
						$start = (int) $mBullet;
					} elseif ($key[0] === 'a' && $mBullet[0] > 'a') {
						$start = ord($mBullet[0]) - 96;
					} elseif ($key[0] === 'A' && $mBullet[0] > 'A') {
						$start = ord($mBullet[0]) - 64;
					}
				}
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$context->getBlockParser()->moveBackward();

		$items = [];
		while ($item = $this->parseItem($context, $bullet, false)) {
			$items[] = $item;
		}

		if (count($items) < $min) {
			return null;
		}

		return new ListNode(
			$items,
			$listType,
			$start,
			Modifier::parse($mMod),
		);
	}


	/**
	 * Parses definition list.
	 * @param  array{string, ?string, string, ?string, string, string}  $matches
	 */
	public function parseDefList(ParseContext $context, array $matches): ?DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->bullets as $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~Ax')) {
				$bullet = $desc[2] ?? $desc[0];
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$context->getBlockParser()->moveBackward(2);

		$items = [];
		$patternTerm = '~^
			\n?
			( \S .* )                       # term content
			: [ \t]*                        # colon separator
			' . Patterns::MODIFIER_H . '?
		$~mUAx';

		while (true) {
			if ($item = $this->parseItem($context, $bullet, true)) {
				$items[] = $item;
				continue;
			}

			$termMatches = null;
			if ($context->getBlockParser()->next($patternTerm, $termMatches)) {
				/** @var array{string, string, ?string} $termMatches */
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod);
				$termContent = $context->parseInline($mContent);
				$items[] = new ListItemNode($termContent, true, $termMod);
				continue;
			}

			break;
		}

		return new DefinitionListNode(
			$items,
			Modifier::parse($mMod),
		);
	}


	/**
	 * Parses single list item.
	 */
	private function parseItem(ParseContext $context, string $bullet, bool $indented): ?ListItemNode
	{
		$spacesBase = $indented ? ('[\ \t]{1,}') : '';
		$patternItem = "~^
			\\n?
			($spacesBase)                            # base indentation (1)
			{$bullet}                                # bullet character
			[ \\t]*
			( \\S .* )?                              # content (2)
			" . Patterns::MODIFIER_H . '?           # modifier (3)
		$~mAUx';

		$matches = null;
		if (!$context->getBlockParser()->next($patternItem, $matches)) {
			return null;
		}

		/** @var array{string, string, ?string, ?string} $matches */
		[, $mIndent, $mContent, $mMod] = $matches;
		$content = ' ' . ($mContent ?? '');

		// a bullet opening the first line is text, - * text is no nested list; the leading space keeps it so
		$isBullet = Regexp::match(
			$mContent ?? '',
			'~(?:' . implode('|', array_column($this->bullets, 0)) . ')~Ax',
		) !== null;

		// next lines
		$spaces = '';
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Amx', $matches)) {
			/** @var array{string, string, string, string} $matches */
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			$content .= "\n" . $mBlank . $mContent;
		}

		return new ListItemNode(
			$context->parseBlock($isBullet ? rtrim($content) : trim($content)),
			false,
			Modifier::parse($mMod),
		);
	}


	public function renderList(ListNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element($node->type->isOrdered() ? 'ol' : 'ul');
		$renderer->decorateElement($node->modifier, $el);

		if ($node->start !== null && $node->start > 1) {
			$el->attrs['start'] = $node->start;
		}

		if ($style = $node->type->getStyleType()) {
			$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
			$el->attrs['style']['list-style-type'] = $style;
		}

		foreach ($node->items as $item) {
			$el->add($this->renderItem($item, $renderer));
		}

		return $el;
	}


	public function renderItem(ListItemNode $node, Html\Renderer $renderer): Html\Element
	{
		// Regular list item (li)
		$el = new Html\Element('li');
		$renderer->decorateElement($node->modifier, $el);
		$el->children = $this->renderItemContent($node->content->children, $renderer);
		return $el;
	}


	public function renderDefinitionList(DefinitionListNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('dl');
		$renderer->decorateElement($node->modifier, $el);

		foreach ($node->items as $item) {
			$el->add($this->renderDefinitionItem($item, $renderer));
		}

		return $el;
	}


	private function renderDefinitionItem(ListItemNode $node, Html\Renderer $renderer): Html\Element
	{
		// Definition list item (dt for term=true, dd for term=false)
		$el = new Html\Element($node->term ? 'dt' : 'dd');
		$renderer->decorateElement($node->modifier, $el);
		$el->children = $node->term
			? $renderer->renderNodes($node->content->children)
			: $this->renderItemContent($node->content->children, $renderer);
		return $el;
	}


	/**
	 * The item is a block container itself, so a wrapper on the block that opens it
	 * adds nothing: <li>text<ul>…</ul></li> needs no <p> around the text. A second
	 * paragraph does get one, because there the wrapper is what separates them.
	 * @param  array<Texy\Node>  $content
	 * @return list<Html\Element|string>
	 */
	private function renderItemContent(array $content, Html\Renderer $renderer): array
	{
		$first = $content[0] ?? null;
		$rest = array_slice($content, 1);

		$opening = match (true) {
			$first instanceof ParagraphNode && $first->modifier === null => $renderer->renderNodes($first->content->children),
			!$rest && $first instanceof FigureNode && $first->caption === null && $first->modifier === null
				=> $renderer->renderNodes([$first->image]),
			default => null,
		};

		return $opening === null
			? $renderer->renderNodes($content)
			: [...$opening, ...$renderer->renderNodes($rest)];
	}
}
