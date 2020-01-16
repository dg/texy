<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Parser for block structures.
 */
class BlockParser extends Parser
{
	/** @var array<string, array{handler: callable, pattern: string}> */
	public $patterns;

	/** @var string */
	private $text;

	/** @var int */
	private $offset;

	/** @var bool */
	private $indented;


	public function __construct(Texy $texy, HtmlElement $element, bool $indented)
	{
		$this->texy = $texy;
		$this->element = $element;
		$this->indented = $indented;
		$this->patterns = $texy->getBlockPatterns();
	}


	public function isIndented(): bool
	{
		return $this->indented;
	}


	// match current line against RE.
	// if succesfull, increments current position and returns true
	public function next(string $pattern, &$matches): bool
	{
		if ($this->offset > strlen($this->text)) {
			return false;
		}
		/** @var array<int, array{string, int}>|null $matches */
		$matches = Regexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			Regexp::OFFSET_CAPTURE,
			$this->offset
		);

		if ($matches) {
			$this->offset += strlen($matches[0][0]) + 1; // 1 = "\n"
			foreach ($matches as $key => $value) {
				$matches[$key] = $value[0];
			}
			return true;
		}
		return false;
	}


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


	public function parse(string $text): void
	{
		$this->texy->invokeHandlers('beforeBlockParse', [$this, &$text]);

		$this->text = $text;
		$this->offset = 0;
		$matches = $this->match($text);
		$matches[] = [strlen($text), null, null]; // terminal sentinel
		$cursor = 0;

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
					$this->texy->paragraphModule->process($this, $s, $this->element);
				}
			}

			if ($mName === null) {
				break; // finito
			}

			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			$res = $this->patterns[$mName]['handler']($this, $mMatches, $mName);

			if ($res === null || $this->offset <= $mOffset) { // module rejects text
				// asi by se nemelo stat, rozdeli generic block
				$this->offset = $mOffset; // turn offset back
				continue;

			} elseif ($res instanceof HtmlElement) {
				$this->element->insert(null, $res);

			} elseif (is_string($res)) {
				$this->element->insert(null, $res);
			}
		} while (1);
	}


	/** @return array<int, array{int, string, array<int, string>, int}> */
	private function match(string $text): array
	{
		$matches = [];
		$priority = 0;
		foreach ($this->patterns as $name => $pattern) {
			/** @var array<int, array<int, array{string, int}>>|null $ms */
			$ms = Regexp::match(
				$text,
				$pattern['pattern'],
				Regexp::OFFSET_CAPTURE | Regexp::ALL
			);

			foreach ((array) $ms as $m) {
				$offset = $m[0][1];
				foreach ($m as $k => $v) {
					$m[$k] = $v[0];
				}
				$matches[] = [$offset, $name, $m, $priority];
			}
			$priority++;
		}
		unset($name, $pattern, $ms, $m, $k, $v);

		usort($matches, function ($a, $b): int {
			if ($a[0] === $b[0]) {
				return $a[3] < $b[3] ? -1 : 1;
			}
			if ($a[0] < $b[0]) {
				return -1;
			}
			return 1;
		});

		return $matches;
	}
}
