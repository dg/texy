<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use Texy\Nodes\TextNode;
use function strlen;


/**
 * Parses inline structures (links, images, formatting, etc.).
 */
class InlineParser
{
	public function __construct(
		/** @var array<string, array{handler: \Closure(ParseContext, array<int|string, ?string>, string): ?Nodes\InlineNode, pattern: string}> */
		public array $patterns,
	) {
	}


	/** @param \Closure(ParseContext, array<int|string, ?string>, string): ?Nodes\InlineNode $handler */
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


	public function parse(?ParseContext $context, string $text): Nodes\ContentNode
	{
		if ($text === '' || !$this->patterns) {
			if ($text === '') {
				return new Nodes\ContentNode;
			}
			return new Nodes\ContentNode([new TextNode($text)]);
		}

		// Find all matches for all patterns
		$allMatches = [];
		foreach ($this->patterns as $name => $info) {
			$matches = Regexp::matchAll($text, $info['pattern'], captureOffset: true);
			foreach ($matches as $match) {
				if ($match[0][0] === '') {
					continue;
				}
				$flatten = [];
				foreach ($match as $key => $value) {
					$flatten[$key] = $value[0];
				}
				$allMatches[] = [
					'name' => $name,
					'offset' => $match[0][1],
					'length' => strlen($match[0][0]),
					'match' => $flatten,
					'handler' => $info['handler'],
				];
			}
		}

		if (!$allMatches) {
			return new Nodes\ContentNode([new TextNode($text)]);
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
				$res[] = new TextNode(substr($text, $pos, $m['offset'] - $pos));
			}

			// Call handler
			$node = ($m['handler'])($context, $m['match'], $m['name']);
			if ($node === null) {
				continue; // Handler rejected - try other patterns at this position
			}

			$res[] = $node;
			$pos = $m['offset'] + $m['length'];
		}

		// Add remaining text
		if ($pos < strlen($text)) {
			$res[] = new TextNode(substr($text, $pos));
		}

		return new Nodes\ContentNode($res);
	}
}
