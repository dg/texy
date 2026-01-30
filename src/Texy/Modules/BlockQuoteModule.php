<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\BlockQuoteNode;
use Texy\ParseContext;
use Texy\Position;
use Texy\Syntax;
use function max, strlen;


/**
 * Processes blockquote syntax with nested content.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				(?: ' . Texy\Patterns::MODIFIER_H . '\n)? # modifier (1)
				>                                      # blockquote char
				( [ \t]++ | : )                        # space/tab or colon (2)
				( \S.*+ )                              # content (3)
			$~mU',
			Syntax::Blockquote,
		);
	}


	/**
	 * Parses blockquote.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?BlockQuoteNode
	{
		[, $mMod, $mPrefix, $mContent] = $matches;

		$startOffset = $offsets[0];
		$totalLength = strlen($matches[0]);
		$contentOffset = $offsets[3] ?? $offsets[0] + 2; // offset of first line content

		// Collect lines with their absolute offsets
		$lines = [['content' => $mContent ?? '', 'offset' => $contentOffset]];
		$spaces = '';

		do {
			if ($spaces === '') {
				$spaces = max(1, strlen($mPrefix));
			}

			if (!$context->getBlockParser()->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mA", $matches, $nextOffsets)) {
				break;
			}

			$totalLength += strlen($matches[0]) + 1; // +1 for \n
			[, $mPrefix, $mContent] = $matches;

			// Track where this line's content starts in absolute terms
			$lineContentOffset = $nextOffsets[2] ?? ($nextOffsets[0] + 2); // after "> "
			$lines[] = ['content' => $mContent ?? '', 'offset' => $lineContentOffset];
		} while (true);

		// Join content for parsing, but track line boundaries
		$content = implode("\n", array_column($lines, 'content'));

		// Parse nested content
		$parsed = $context->parseBlock(trim($content));
		if (!$parsed->children) {
			return null;
		}

		// Build offset map: [localOffset => absoluteOffset] for each line start
		$offsetMap = [];
		$localPos = 0;
		foreach ($lines as $line) {
			$offsetMap[$localPos] = $line['offset'];
			$localPos += strlen((string) $line['content']) + 1; // +1 for \n
		}

		// Fix positions in parsed content using offset map
		$this->fixPositions($parsed, $offsetMap, $lines);

		return new BlockQuoteNode(
			$parsed,
			Texy\Modifier::parse($mMod),
			new Position($startOffset, $totalLength),
		);
	}


	/**
	 * Fix positions in parsed content using offset mapping.
	 * For content spanning multiple lines, adjusts offset and length.
	 * @param array<int, int> $offsetMap [localLineStart => absoluteLineStart]
	 * @param array<array{content: string, offset: int}> $lines
	 */
	private function fixPositions(Texy\Node $node, array $offsetMap, array $lines): void
	{
		if ($node->position !== null) {
			$localOffset = $node->position->offset;
			$localLength = $node->position->length;

			// Find which line this position starts on
			$lineIndex = 0;
			$lineStart = 0;
			$lineKeys = array_keys($offsetMap);
			foreach ($lineKeys as $i => $localLineStart) {
				if ($localLineStart <= $localOffset) {
					$lineIndex = $i;
					$lineStart = $localLineStart;
				} else {
					break;
				}
			}

			$absoluteStart = $offsetMap[$lineStart];
			$newOffset = $absoluteStart + ($localOffset - $lineStart);

			// Check if content spans multiple lines
			$localEnd = $localOffset + $localLength;
			$endLineIndex = $lineIndex;
			foreach ($lineKeys as $i => $localLineStart) {
				if ($localLineStart < $localEnd) {
					$endLineIndex = $i;
				}
			}

			if ($endLineIndex > $lineIndex) {
				// Content spans multiple lines - calculate correct length
				// Each line boundary in original has extra characters ("> ")
				$extraChars = 0;
				for ($i = $lineIndex + 1; $i <= $endLineIndex; $i++) {
					// Each new line in original has "> " prefix (2 chars) that's not in transformed
					$extraChars += 2;
				}
				$newLength = $localLength + $extraChars;
			} else {
				$newLength = $localLength;
			}

			$node->position = new Position($newOffset, $newLength);
		}

		foreach ($node->getNodes() as $child) {
			$this->fixPositions($child, $offsetMap, $lines);
		}
	}
}
