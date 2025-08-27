<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use Texy\{Nodes, Range, Regexp};
use Texy\Nodes\TextNode;
use function strlen;


/**
 * Parses inline structures (links, images, formatting, etc.).
 */
class InlineParser
{
	public function __construct(
		/** @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}> */
		public array $patterns,
	) {
	}


	/** @param \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode $handler */
	public function addPattern(string $name, string $pattern, \Closure $handler): void
	{
		$this->patterns[$name] = [
			'pattern' => $pattern,
			'handler' => $handler,
		];
	}


	/**
	 * Returns a clone with only specified patterns.
	 * @param array<string> $names Pattern names to keep
	 */
	public function withPatterns(array $names): self
	{
		$clone = clone $this;
		$clone->patterns = array_intersect_key($this->patterns, array_flip($names));
		return $clone;
	}


	/**
	 * First match of one pattern at or after the offset, in the shape the loop works with.
	 * @param  array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode, pattern: string}  $info
	 * @return ?array{name: string, offset: int, length: int, match: array<?string>, offsets: array<?int>, handler: \Closure(ParseContext, array<?string>, array<?int>, string): ?Nodes\InlineNode}
	 */
	private function match(string $text, array $info, string $name, int $offset): ?array
	{
		while ($offset <= strlen($text)) {
			$m = Regexp::match($text, $info['pattern'], captureOffset: true, offset: $offset);
			if ($m === null) {
				return null;
			}

			// a zero-length match would never move the position forward
			if ($m[0][0] === null || $m[0][0] === '') {
				$offset = $m[0][1] + 1;
				continue;
			}

			$flatten = $offsets = [];
			foreach ($m as $key => $value) {
				$flatten[$key] = $value[0];
				$offsets[$key] = $value[0] === null ? null : $value[1];
			}

			return [
				'name' => $name,
				'offset' => $m[0][1],
				'length' => strlen($m[0][0]),
				'match' => $flatten,
				'offsets' => $offsets,
				'handler' => $info['handler'],
			];
		}

		return null;
	}


	public function parse(ParseContext $context, string $text, int $baseOffset = 0): Nodes\ContentNode
	{
		if ($text === '' || !$this->patterns) {
			if ($text === '') {
				return new Nodes\ContentNode;
			}
			return new Nodes\ContentNode([new TextNode($text, new Range($baseOffset, strlen($text)))]);
		}

		// One candidate per pattern, refreshed as the position moves. Searching every
		// pattern once up front would lose matches: a pattern returns non-overlapping
		// matches, so a wrong early one swallows the region where the right one sits.
		// `**` inside a code span used to pair with the next `**` this way, and the
		// real pair behind it was never offered.
		$candidates = [];
		foreach ($this->patterns as $name => $info) {
			$candidates[$name] = $this->match($text, $info, $name, 0);
		}

		$res = [];
		$pos = 0;

		while (true) {
			$best = null;
			foreach ($candidates as $name => $m) {
				if ($m !== null && $m['offset'] < $pos) {
					$candidates[$name] = $m = $this->match($text, $this->patterns[$name], $name, $pos);
				}
				// leftmost wins, longer match on a tie
				if ($m !== null && ($best === null || [$m['offset'], -$m['length']] < [$best['offset'], -$best['length']])) {
					$best = $m;
				}
			}

			if ($best === null) {
				break;
			}

			// Adjust offsets to absolute positions
			$absoluteOffsets = [];
			foreach ($best['offsets'] as $key => $offset) {
				$absoluteOffsets[$key] = $offset !== null ? $baseOffset + $offset : null;
			}

			// Call handler with offsets
			$node = ($best['handler'])($context, $best['match'], $absoluteOffsets, $best['name']);
			if ($node === null) {
				// Handler rejected - other patterns at this position still get their turn
				$candidates[$best['name']] = $this->match($text, $this->patterns[$best['name']], $best['name'], $best['offset'] + 1);
				continue;
			}

			// Add text before this match (only now - a rejected match must not emit it)
			if ($best['offset'] > $pos) {
				$res[] = new TextNode(
					substr($text, $pos, $best['offset'] - $pos),
					new Range($baseOffset + $pos, $best['offset'] - $pos),
				);
			}

			$res[] = $node;
			$pos = $best['offset'] + $best['length'];
		}

		// Add remaining text
		if ($pos < strlen($text)) {
			$res[] = new TextNode(
				substr($text, $pos),
				new Range($baseOffset + $pos, strlen($text) - $pos),
			);
		}

		return new Nodes\ContentNode($res);
	}
}
