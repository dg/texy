<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use Texy\Nodes\InlineNode;
use Texy\Nodes\TextNode;
use function strlen;


/**
 * Parses inline structures (links, images, formatting, etc.).
 */
class InlineParser
{
	public function __construct(
		protected Texy $texy,
		/** @var array<string, array{handler: \Closure(InlineParser, array<string>, string, array<int|string, int|null>): ?InlineNode, pattern: string}> */
		public array $patterns,
	) {
	}


	public function addPattern(string $name, string $pattern, \Closure $handler): void
	{
		$this->patterns[$name] = [
			'pattern' => $pattern,
			'handler' => $handler,
		];
	}


	/**
	 * @param int $baseOffset Base offset to add for absolute positions (for nested content)
	 * @return array<InlineNode>
	 */
	public function parse(string $text, int $baseOffset = 0): array
	{
		if ($text === '' || !$this->patterns) {
			if ($text === '') {
				return [];
			}
			return [new TextNode($text, new Position($baseOffset, strlen($text)))];
		}

		// Find all matches for all patterns
		$allMatches = [];
		foreach ($this->patterns as $name => $info) {
			$matches = Regexp::matchAll($text, $info['pattern'], captureOffset: true);
			foreach ($matches as $match) {
				if ($match[0][0] === '') {
					continue;
				}
				$flatten = $offsets = [];
				foreach ($match as $key => $value) {
					$flatten[$key] = $value[0];
					$offsets[$key] = $value[1] >= 0 ? $value[1] : null;
				}
				$allMatches[] = [
					'name' => $name,
					'offset' => $match[0][1],
					'length' => strlen($match[0][0]),
					'match' => $flatten,
					'offsets' => $offsets,
					'handler' => $info['handler'],
				];
			}
		}

		if (!$allMatches) {
			return [new TextNode($text, new Position($baseOffset, strlen($text)))];
		}

		// Sort by offset, longer matches first for same offset
		usort($allMatches, fn($a, $b) => $a['offset'] <=> $b['offset'] ?: $b['length'] <=> $a['length']);

		// Process matches without overlapping
		$res = [];
		$pos = 0;

		foreach ($allMatches as $m) {
			if ($m['offset'] < $pos) {
				continue; // Skip overlapping match
			}

			// Add text before this match
			if ($m['offset'] > $pos) {
				$res[] = new TextNode(
					substr($text, $pos, $m['offset'] - $pos),
					new Position($baseOffset + $pos, $m['offset'] - $pos),
				);
			}

			// Adjust offsets to absolute positions
			$absoluteOffsets = [];
			foreach ($m['offsets'] as $key => $offset) {
				$absoluteOffsets[$key] = $offset !== null ? $baseOffset + $offset : null;
			}

			// Call handler with offsets
			$node = ($m['handler'])($this, $m['match'], $m['name'], $absoluteOffsets);
			if ($node !== null) {
				$res[] = $node;
				$pos = $m['offset'] + $m['length'];
			} else {
				// Handler returned null, treat as text
				$res[] = new TextNode(
					substr($text, $m['offset'], $m['length']),
					new Position($baseOffset + $m['offset'], $m['length']),
				);
				$pos = $m['offset'] + $m['length'];
			}
		}

		// Add remaining text
		if ($pos < strlen($text)) {
			$res[] = new TextNode(
				substr($text, $pos),
				new Position($baseOffset + $pos, strlen($text)),
			);
		}

		return $res;
	}
}
