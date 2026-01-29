<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Nodes\DefinitionListNode;
use Texy\Nodes\ListItemNode;
use Texy\Nodes\ListNode;
use Texy\Nodes\ListType;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use Texy\Syntax;
use function count, implode, ord, strlen;


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
		$texy->allowed[Syntax::List] = true;
		$texy->allowed[Syntax::DefinitionList] = true;
		$texy->htmlGenerator->registerHandler($this->solveList(...));
		$texy->htmlGenerator->registerHandler($this->solveItem(...));
		$texy->htmlGenerator->registerHandler($this->solveDefList(...));
		$texy->htmlGenerator->registerHandler($this->solveDefItem(...));
	}


	public function beforeParse(string &$text): void
	{
		$RE = $REul = [];
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]->isOrdered()) {
				$REul[] = $desc[0];
			}
		}

		$this->texy->registerBlockPattern(
			$this->parseList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $RE) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mU',
			Syntax::List,
		);

		$this->texy->registerBlockPattern(
			$this->parseDefList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)?   # modifier (1)
				( \S .{0,2000} )                      # definition term (2)
				: [ \t]*                              # colon separator
				' . Patterns::MODIFIER_H . '?         # modifier (3)
				\n
				([ \t]++)                             # indentation (4)
				(' . implode('|', $REul) . ')         # description marker (5)
				[ \t]*+
				\S .*                                 # content
			$~mU',
			Syntax::DefinitionList,
		);
	}


	/**
	 * Parses list.
	 * @param  array<?string>  $matches
	 */
	public function parseList(ParseContext $context, array $matches): ?ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$bullet = null;
		$min = 1;
		$start = null;
		$listType = ListType::Unordered;

		foreach ($this->bullets as $key => $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
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

		$context->getBlockParser()->moveBackward(1);

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
	 * @param  array<?string>  $matches
	 */
	public function parseDefList(ParseContext $context, array $matches): ?DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->bullets as $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
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
		$~mUA';

		while (true) {
			if ($item = $this->parseItem($context, $bullet, true)) {
				$items[] = $item;
				continue;
			}

			$termMatches = null;
			if ($context->getBlockParser()->next($patternTerm, $termMatches)) {
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
		$~mAU';

		$matches = null;
		if (!$context->getBlockParser()->next($patternItem, $matches)) {
			return null;
		}

		[, $mIndent, $mContent, $mMod] = $matches;

		// Collect content lines
		$spaces = '';
		$content = ' ' . ($mContent ?? '');
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Am', $matches)) {
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			$content .= "\n" . $mBlank . $mContent;
		}

		// Parse content as blocks
		$parsed = $context->parseBlock(trim($content));

		return new ListItemNode(
			$parsed,
			false,
			Modifier::parse($mMod),
		);
	}


	public function solveList(ListNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element($node->type->isOrdered() ? 'ol' : 'ul');
		$node->modifier?->decorate($this->texy, $el);

		if ($node->start !== null && $node->start > 1) {
			$el->attrs['start'] = $node->start;
		}

		if ($style = $node->type->getStyleType()) {
			$styles = is_array($el->attrs['style'] ?? null) ? $el->attrs['style'] : [];
			$styles['list-style-type'] = $style;
			$el->attrs['style'] = $styles;
		}

		foreach ($node->items as $item) {
			$el->add($this->solveItem($item, $generator));
		}

		return $el;
	}


	public function solveItem(ListItemNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element('li');
		$node->modifier?->decorate($this->texy, $el);

		// If first child is a simple ParagraphNode (no modifier), unwrap it
		$content = $node->content->children;
		if (
			count($content) === 1
			&& $content[0] instanceof Nodes\ParagraphNode
			&& $content[0]->modifier === null
		) {
			$el->children = $generator->renderNodes($content[0]->content->children);
		} else {
			$el->children = $generator->renderNodes($content);
		}

		return $el;
	}


	public function solveDefList(DefinitionListNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element('dl');
		$node->modifier?->decorate($this->texy, $el);

		foreach ($node->items as $item) {
			$el->add($this->solveDefItem($item, $generator));
		}

		return $el;
	}


	public function solveDefItem(ListItemNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element($node->term ? 'dt' : 'dd');
		$node->modifier?->decorate($this->texy, $el);

		// If content is a single simple ParagraphNode (no modifier), unwrap it
		$content = $node->content->children;
		if (
			!$node->term
			&& count($content) === 1
			&& $content[0] instanceof Nodes\ParagraphNode
			&& $content[0]->modifier === null
		) {
			$el->children = $generator->renderNodes($content[0]->content->children);
		} else {
			$el->children = $generator->renderNodes($content);
		}

		return $el;
	}
}
