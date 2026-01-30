<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\DefinitionListNode;
use Texy\Nodes\ListItemNode;
use Texy\Nodes\ListNode;
use Texy\Nodes\ListType;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Position;
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
	 * @param  array<?int>  $offsets
	 */
	public function parseList(ParseContext $context, array $matches, array $offsets): ?ListNode
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
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses definition list.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseDefList(ParseContext $context, array $matches, array $offsets): ?DefinitionListNode
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
			$termOffsets = null;
			if ($context->getBlockParser()->next($patternTerm, $termMatches, $termOffsets)) {
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod);
				$contentOffset = $termOffsets[1] ?? 0;
				$termContent = $context->parseInline($mContent, $contentOffset);
				$items[] = new ListItemNode($termContent, true, $termMod);
				continue;
			}

			break;
		}

		return new DefinitionListNode(
			$items,
			Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
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
		$offsets = null;
		if (!$context->getBlockParser()->next($patternItem, $matches, $offsets)) {
			return null;
		}

		[, $mIndent, $mContent, $mMod] = $matches;

		// Collect lines with their absolute offsets
		$lines = [];
		$contentOffset = $offsets[2] ?? $offsets[0]; // offset of first line content
		if ($mContent !== null) {
			$lines[] = ['content' => $mContent, 'offset' => $contentOffset];
		}

		// next lines
		$spaces = '';
		$content = ' ' . ($mContent ?? '');
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Am', $matches, $offsets)) {
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			$content .= "\n" . $mBlank . $mContent;

			// Track content offset
			if ($mContent !== '' && $offsets[3] !== null) {
				$lines[] = ['content' => $mContent, 'offset' => $offsets[3]];
			}
		}

		// Parse content as blocks
		$parsed = $context->parseBlock(trim($content));

		// Fix positions in parsed content using offset mapping
		if ($lines) {
			$this->fixPositions($parsed, $lines);
		}

		return new ListItemNode(
			$parsed,
			false,
			Modifier::parse($mMod),
		);
	}


	/**
	 * Fix positions in parsed content using offset mapping.
	 * @param array<array{content: string, offset: int}> $lines
	 */
	private function fixPositions(Texy\Node $node, array $lines): void
	{
		if ($node->position !== null) {
			$localOffset = $node->position->offset;

			// Find which line this position starts on
			$offsetMap = [];
			$localPos = 0;
			foreach ($lines as $line) {
				$offsetMap[$localPos] = $line['offset'];
				$localPos += strlen($line['content']) + 1; // +1 for \n
			}

			$lineStart = 0;
			$lineKeys = array_keys($offsetMap);
			foreach ($lineKeys as $localLineStart) {
				if ($localLineStart <= $localOffset) {
					$lineStart = $localLineStart;
				} else {
					break;
				}
			}

			$absoluteStart = $offsetMap[$lineStart] ?? $lines[0]['offset'];
			$newOffset = $absoluteStart + ($localOffset - $lineStart);

			$node->position = new Position($newOffset, $node->position->length);
		}

		foreach ($node->getNodes() as $child) {
			$this->fixPositions($child, $lines);
		}
	}
}
