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
 * A handler returns an InlineNode, a ContentNode (sequence to splice; empty
 * consumes silently), or null to refuse the match. A syntax with a list of patterns
 * matches where the leftmost of them does, the earlier one on a tie.
 */
final class InlineParser
{
	public function __construct(
		/** @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null), pattern: string|list<string>}> */
		private array $patterns,
	) {
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
		// `**` inside a code span would pair with the next `**` this way, and the
		// real pair behind it would never be offered.
		$candidates = [];
		foreach ($this->patterns as $name => $info) {
			foreach ((array) $info['pattern'] as $variant => $_) {
				$candidates[$name][$variant] = $this->match($text, $name, $variant, 0);
			}
		}

		$res = [];
		$pos = 0;

		while (true) {
			$best = null;
			foreach ($candidates as $name => $variants) {
				$own = null;
				foreach ($variants as $variant => $m) {
					if ($m !== null && $m['offset'] < $pos) {
						$candidates[$name][$variant] = $m = $this->match($text, $name, $variant, $pos);
					}
					if ($m !== null && ($own === null || $m['offset'] < $own['offset'])) {
						$own = $m;
					}
				}
				// leftmost wins, longer match on a tie
				if ($own !== null && ($best === null || [$own['offset'], -$own['length']] < [$best['offset'], -$best['length']])) {
					$best = $own;
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
				$candidates[$best['name']][$best['variant']] = $this->match($text, $best['name'], $best['variant'], $best['offset'] + 1);
				continue;
			}

			// Add text before this match (only now - a rejected match must not emit it)
			if ($best['offset'] > $pos) {
				$res[] = new TextNode(
					substr($text, $pos, $best['offset'] - $pos),
					new Range($baseOffset + $pos, $best['offset'] - $pos),
				);
			}

			if ($node instanceof Nodes\ContentNode) {
				array_push($res, ...$node->children); // a returned sequence is spliced in
			} else {
				$res[] = $node;
			}

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


	/**
	 * First match of one pattern of the syntax at or after the offset, in the shape the loop works with.
	 * @return ?array{name: string, variant: int, offset: int, length: int, match: array<?string>, offsets: array<?int>, handler: \Closure(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null)}
	 */
	private function match(string $text, string $name, int $variant, int $offset): ?array
	{
		$info = $this->patterns[$name];
		$pattern = ((array) $info['pattern'])[$variant];
		while ($offset <= strlen($text)) {
			$m = Regexp::match($text, $pattern, captureOffset: true, offset: $offset);
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
				'variant' => $variant,
				'offset' => $m[0][1],
				'length' => strlen($m[0][0]),
				'match' => $flatten,
				'offsets' => $offsets,
				'handler' => $info['handler'],
			];
		}

		return null;
	}
}
