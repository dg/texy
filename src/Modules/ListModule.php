<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Range, Regexp, Syntax};
use Texy\Nodes\{DefinitionListNode, ListItemNode, ListNode, ListType};
use Texy\Parsing\ParseContext;
use function count, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$re = $reUl = [];
		foreach ($this->config->listBullets as $desc) {
			$re[] = $desc[0];
			if (!$desc[1]->isOrdered()) {
				$reUl[] = $desc[0];
			}
		}

		$registry->addBlock(
			Syntax::List,
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $re) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mUx',
			$this->parseList(...),
		);

		$registry->addBlock(
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
	 * @param  array{int, ?int, int}  $offsets
	 */
	public function parseList(ParseContext $context, array $matches, array $offsets): ?ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$bullet = null;
		$min = 1;
		$start = null;
		$listType = ListType::Unordered;

		foreach ($this->config->listBullets as $key => $desc) {
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
			Modifier::parse($mMod, $offsets[1]),
			$this->createRange($offsets[0], $items),
		);
	}


	/**
	 * Parses definition list.
	 * @param  array{string, ?string, string, ?string, string, string}  $matches
	 * @param  array{int, ?int, int, ?int, int, int}  $offsets
	 */
	public function parseDefList(ParseContext $context, array $matches, array $offsets): ?DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->config->listBullets as $desc) {
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
			$termOffsets = null;
			if ($context->getBlockParser()->next($patternTerm, $termMatches, $termOffsets)) {
				/** @var array{string, string, ?string} $termMatches */
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod, $termOffsets[2] ?? null);
				// groups 0 and 1 always participate in a successful match, but next() cannot type that
				$termOffset = $termOffsets[0] ?? throw new \LogicException('Match without group 0.');
				$contentOffset = $termOffsets[1] ?? throw new \LogicException('Match without group 1.');
				$termContent = $context->parseInline($mContent, $contentOffset);
				$items[] = new ListItemNode($termContent, true, $termMod, new Range($termOffset, strlen($termMatches[0])));
				continue;
			}

			break;
		}

		if (!$items) {
			return null;
		}

		return new DefinitionListNode(
			$items,
			Modifier::parse($mMod, $offsets[1]),
			$this->createRange($offsets[0], $items),
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
		$offsets = null;
		if (!$context->getBlockParser()->next($patternItem, $matches, $offsets)) {
			return null;
		}

		/** @var array{string, string, ?string, ?string} $matches */
		[, $mIndent, $mContent, $mMod] = $matches;
		// group 0 always participates in a successful match, but next() cannot type that
		$itemOffset = $offsets[0] ?? throw new \LogicException('Match without group 0.');
		$modOffset = $offsets[3] ?? null;
		$itemEnd = $itemOffset + strlen($matches[0]);

		// Assemble content and map local line starts to absolute source offsets
		$map = [];
		$content = ' ' . ($mContent ?? '');
		if ($mContent !== null) {
			// content matched, so group 2 participated and carries an offset
			$map[1] = $offsets[2] ?? throw new \LogicException('Content without group 2.'); // 1 = the leading space
		}

		// a bullet opening the first line is text, - * text is no nested list; the leading space keeps it so
		$isBullet = Regexp::match(
			$mContent ?? '',
			'~(?:' . implode('|', array_column($this->config->listBullets, 0)) . ')~Ax',
		) !== null;

		// next lines
		$spaces = '';
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Amx', $matches, $offsets)) {
			/** @var array{string, string, string, string} $matches */
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			if ($mContent !== '' && $offsets[3] !== null) {
				$map[strlen($content) + 1 + strlen($mBlank)] = $offsets[3]; // 1 = "\n"
			}

			$content .= "\n" . $mBlank . $mContent;
			$itemEnd = ($offsets[0] ?? throw new \LogicException('Match without group 0.')) + strlen($matches[0]);
		}

		$text = $isBullet ? rtrim($content) : trim($content);
		$parsed = $context->parseBlock($text);
		if ($map) {
			new Texy\Parsing\OffsetMap($map, strlen(rtrim($content)) - strlen($text))->applyTo($parsed);
		}

		return new ListItemNode(
			$parsed,
			false,
			Modifier::parse($mMod, $modOffset),
			new Range($itemOffset, $itemEnd - $itemOffset),
		);
	}


	/**
	 * Range of a whole list, from its start to the end of its last item.
	 * @param  non-empty-list<ListItemNode>  $items
	 */
	private function createRange(int $offset, array $items): Range
	{
		$last = $items[count($items) - 1]->range ?? throw new \LogicException('List item without range.');
		return new Range($offset, $last->offset + $last->length - $offset);
	}
}
