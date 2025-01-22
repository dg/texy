<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Parser for single line structures.
 */
class LineParser extends Parser
{
	/** @var array<string, array{handler: callable, pattern: string, again: ?string}> */
	public array $patterns;
	public bool $again;


	public function __construct(Texy $texy)
	{
		$this->texy = $texy;
		$this->patterns = $texy->getLinePatterns();
	}


	public function parse(string $text): array
	{
		if (!$this->patterns) { // nothing to do
			return [$text];
		}

		$offset = 0;
		$names = array_keys($this->patterns);
		/** @var array<string, array<int, array{string, int}>> $matches */
		$matches = $offsets = [];
		foreach ($names as $name) {
			$offsets[$name] = -1;
		}

		do {
			$first = $this->match($text, $offset, $names, $offsets, $matches);
			if ($first === null) {
				break;
			}

			$px = $this->patterns[$first];
			$offset = $start = $offsets[$first];

			$this->again = false;
			$res = $px['handler']($this, $matches[$first], $first);

			if ($res instanceof HtmlElement) {
				$res = $this->texy->elemToMaskedString($res);
			} elseif ($res === null) {
				$offsets[$first] = -2;
				continue;
			}

			$len = strlen($matches[$first][0]);
			$text = substr_replace(
				$text,
				(string) $res,
				$start,
				$len,
			);

			$delta = strlen($res) - $len;
			foreach ($names as $name) {
				if ($offsets[$name] < $start + $len) {
					$offsets[$name] = -1;
				} else {
					$offsets[$name] += $delta;
				}
			}

			if ($this->again) {
				$offsets[$first] = -2;
			} else {
				$offsets[$first] = -1;
				$offset += strlen($res);
			}
		} while (1);

		return [$text];
	}


	private function match(string $text, int $offset, array &$names, array &$offsets, array &$matches): ?string
	{
		$first = null;
		$minOffset = strlen($text);

		foreach ($names as $index => $name) {
			if ($offsets[$name] < $offset) {
				$delta = 0;
				if ($offsets[$name] === -2) {
					do {
						$delta++;
					} while (
						isset($text[$offset + $delta])
						&& $text[$offset + $delta] >= "\x80"
						&& $text[$offset + $delta] < "\xC0"
					);
				}

				if ($offset + $delta > strlen($text)) {
					unset($names[$index]);
					continue;

				} elseif ($matches[$name] = Regexp::match(
					$text,
					$this->patterns[$name]['pattern'],
					captureOffset: true,
					offset: $offset + $delta,
				)) {
					$m = &$matches[$name];
					if (!strlen($m[0][0])) {
						continue;
					}

					$offsets[$name] = $m[0][1];
					foreach ($m as $keyx => $value) {
						$m[$keyx] = $value[0];
					}
				} else {
					// try next time?
					if (
						!$this->patterns[$name]['again']
						|| !Regexp::match($text, $this->patterns[$name]['again'], offset: $offset + $delta)
					) {
						unset($names[$index]);
					}

					continue;
				}
			}

			if ($offsets[$name] < $minOffset) {
				$minOffset = $offsets[$name];
				$first = $name;
			}
		}

		return $first;
	}
}
