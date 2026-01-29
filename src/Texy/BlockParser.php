<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use function is_array, is_string, max, strlen, substr, trim, usort;



/**
 * Parses block structures (paragraphs, headings, lists, tables, etc.).
 */
class BlockParser extends Parser
{
	private string $text;
	private int $offset;


	public function __construct(
		protected Texy $texy,
		private bool $indented,
		/** @var array<string, array{handler: \Closure(BlockParser, array<?string>, string): (HtmlElement|string|null), pattern: string}> */
		public array $patterns,
	) {
	}


	public function isIndented(): bool
	{
		return $this->indented;
	}


	/** @return list<HtmlElement|string> */
	public function parse(string $text): array
	{
		$this->texy->invokeHandlers('beforeBlockParse', [$this, &$text]);

		$this->text = $text;
		$this->offset = 0;
		$matches = $this->match($text);
		$matches[] = [strlen($text), null, null]; // terminal sentinel
		$cursor = 0;
		$res = [];

		do {
			do {
				[$mOffset, $mName, $mMatches] = $matches[$cursor];
				$cursor++;
				if ($mName === null || $mOffset >= $this->offset) {
					break;
				}
			} while (1);

			// between-matches content
			if ($mOffset > $this->offset) {
				$s = trim(substr($text, $this->offset, $mOffset - $this->offset));
				if ($s !== '') {
					$res = array_merge($res, $this->texy->paragraphModule->process($this, $s));
				}
			}

			if ($mName === null) {
				break; // finito
			}

			assert(is_array($mMatches) && is_string($mMatches[0]));
			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			$el = $this->patterns[$mName]['handler']($this, $mMatches, $mName);

			if ($el === null || $this->offset <= $mOffset) { // module rejects text
				// asi by se nemelo stat, rozdeli generic block
				$this->offset = $mOffset; // turn offset back

			} else {
				$res[] = $el;
			}
		} while (1);

		return $res;
	}


	/**
	 * Match current line against RE.
	 * If successful, increments current position and returns true.
	 * @param  ?array<?string>  $matches
	 * @param-out array<?string> $matches
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
		/** @var ?array<array{?string, int}> $m */
		$m = Regexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			captureOffset: true,
			offset: $this->offset,
		);

		if ($m) {
			assert(is_string($m[0][0]));
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


	/** @return list<array{int, ?string, ?array<?string>, int}> */
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
