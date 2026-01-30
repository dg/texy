<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Nodes\BlockNode;


/**
 * Parses block structures (paragraphs, headings, lists, tables, etc.).
 */
class BlockParser
{
	private string $text;
	private int $offset;


	/**
	 * @param array<string, array{handler: \Closure(ParseContext, array<string>, string): ?BlockNode, pattern: string}> $patterns
	 * @param \Closure(ParseContext, string): array<BlockNode> $gapHandler
	 */
	public function __construct(
		private array $patterns,
		private \Closure $gapHandler,
	) {
	}


	public function parse(ParseContext $context, string $text): Nodes\ContentNode
	{
		$this->text = $text;
		$this->offset = 0;
		$matches = $this->match($text);
		$matches[] = [strlen($text), null, null, 0]; // terminal sentinel
		$cursor = 0;
		$res = [];

		do {
			do {
				[$mOffset, $mName, $mMatches] = $matches[$cursor];
				$cursor++;
				if ($mName === null || $mOffset >= $this->offset) {
					break;
				}
			} while (true);

			// between-matches content → delegate to gap handler
			if ($mOffset > $this->offset) {
				$s = substr($text, $this->offset, $mOffset - $this->offset);
				$res = array_merge($res, ($this->gapHandler)($context, $s));
			}

			if ($mName === null) {
				break; // finito
			}

			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			// call handler
			$handler = $this->patterns[$mName]['handler'];
			$node = $handler($context, $mMatches, $mName);

			if ($node === null || $this->offset <= $mOffset) { // handler rejects text
				$this->offset = $mOffset;
				continue;
			}

			$res[] = $node;
		} while (true);

		return new Nodes\ContentNode($res);
	}


	/**
	 * Match current line against RE.
	 * If successful, increments current position and returns true.
	 * @param  ?array<string>  $matches
	 * @param-out array<string> $matches
	 */
	public function next(
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		?array &$matches,
	): bool
	{
		if ($this->offset > strlen($this->text)) {
			return false;
		}

		$matches = [];
		/** @var ?array<array{string, int}> $m */
		$m = Regexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			captureOffset: true,
			offset: $this->offset,
		);

		if ($m) {
			$this->offset += strlen($m[0][0]) + 1; // 1 = "\n"
			foreach ($m as $key => $value) {
				$matches[$key] = $value[0];
			}

			return true;
		}

		return false;
	}


	/**
	 * Moves position back by specified number of lines.
	 */
	public function moveBackward(int $linesCount = 1): void
	{
		while (--$this->offset > 0) {
			if ($this->text[$this->offset - 1] === "\n") {
				$linesCount--;
				if ($linesCount < 1) {
					break;
				}
			}
		}

		$this->offset = max($this->offset, 0);
	}


	/** @return list<array{int, ?string, ?array<int|string, string|null>, int}> */
	private function match(string $text): array
	{
		$matches = [];
		$priority = 0;

		foreach ($this->patterns as $name => $pattern) {
			$ms = Regexp::matchAll(
				$text,
				$pattern['pattern'],
				captureOffset: true,
			);

			foreach ($ms as $m) {
				$offset = $m[0][1];
				foreach ($m as $k => $v) {
					$m[$k] = $v[0];
				}

				$matches[] = [$offset, $name, $m, $priority];
			}

			$priority++;
		}

		usort($matches, fn($a, $b): int => $a[0] <=> $b[0] ?: $a[3] <=> $b[3]);
		return $matches;
	}
}
