<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_keys, strlen, substr_replace;


/**
 * Parses inline structures (links, images, formatting, etc.).
 */
class LineParser extends Parser
{
	/** @var array<string, array{handler: \Closure(LineParser, array<string>, string): (HtmlElement|string|null), pattern: string, again: ?string}> */
	public array $patterns;
	public bool $again;


	public function __construct(Texy $texy, HtmlElement $element)
	{
		$this->texy = $texy;
		$this->element = $element;
		$this->patterns = $texy->getLinePatterns();
	}


	/**
	 * Parses text and appends results to parent element.
	 */
	public function parse(string $text): void
	{
		if (!$this->patterns) { // nothing to do
			$this->element->insert(null, $text);
			return;
		}

		$offset = 0;
		$names = array_keys($this->patterns);
		/** @var array<string, array<string>> $matches */
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
				$res = $res->toString($this->texy);
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

		$this->element->insert(null, $text);
	}


	/**
	 * @param  array<int, string>  $names
	 * @param  array<string, int>  $offsets
	 * @param  array<string, array<string>>  $matches
	 */
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
					Regexp::OFFSET_CAPTURE,
					$offset + $delta,
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
						|| !Regexp::match($text, $this->patterns[$name]['again'], 0, $offset + $delta)
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
