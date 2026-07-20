<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

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


	public function parse(ParseContext $context, string $text, int $baseOffset = 0): Nodes\ContentNode
	{
		if ($text === '' || !$this->patterns) {
			if ($text === '') {
				return new Nodes\ContentNode;
			}
			return new Nodes\ContentNode([new TextNode(Helpers::decodeEntities($text), new Range($baseOffset, strlen($text)))]);
		}

		// Find all matches for all patterns
		$allMatches = [];
		foreach ($this->patterns as $name => $info) {
			$matches = Regexp::matchAll($text, $info['pattern'], captureOffset: true);
			foreach ($matches as $match) {
				/** @var array<array{string|null, int}> $match */
				$fullMatch = (string) $match[0][0];
				if ($fullMatch === '') {
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
					'length' => strlen($fullMatch),
					'match' => $flatten,
					'offsets' => $offsets,
					'handler' => $info['handler'],
				];
			}
		}

		if (!$allMatches) {
			return new Nodes\ContentNode([new TextNode(Helpers::decodeEntities($text), new Range($baseOffset, strlen($text)))]);
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

			// Adjust offsets to absolute positions
			$absoluteOffsets = [];
			foreach ($m['offsets'] as $key => $offset) {
				$absoluteOffsets[$key] = $offset !== null ? $baseOffset + $offset : null;
			}

			// Call handler with offsets
			$node = ($m['handler'])($context, $m['match'], $absoluteOffsets, $m['name']);
			if ($node === null) {
				continue; // Handler rejected - try other patterns at this position
			}

			// Add text before this match (only now - a rejected match must not emit it)
			if ($m['offset'] > $pos) {
				$res[] = new TextNode(
					Helpers::decodeEntities(substr($text, $pos, $m['offset'] - $pos)),
					new Range($baseOffset + $pos, $m['offset'] - $pos),
				);
			}

			$res[] = $node;
			$pos = $m['offset'] + $m['length'];
		}

		// Add remaining text
		if ($pos < strlen($text)) {
			$res[] = new TextNode(
				Helpers::decodeEntities(substr($text, $pos)),
				new Range($baseOffset + $pos, strlen($text) - $pos),
			);
		}

		return new Nodes\ContentNode($res);
	}
}
