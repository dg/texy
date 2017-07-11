<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Parser for single line structures.
 */
class LineParser extends Parser
{
	/** @var bool */
	public $again;


	public function __construct(Texy $texy, HtmlElement $element)
	{
		$this->texy = $texy;
		$this->element = $element;
		$this->patterns = $texy->getLinePatterns();
	}


	/**
	 * @param  string
	 * @return void
	 */
	public function parse($text)
	{
		// initialization
		$pl = $this->patterns;
		if (!$pl) {
			// nothing to do
			$this->element->insert(null, $text);
			return;
		}

		$offset = 0;
		$names = array_keys($pl);
		$arrMatches = $arrOffset = [];
		foreach ($names as $name) {
			$arrOffset[$name] = -1;
		}


		// parse loop
		do {
			$min = null;
			$minOffset = strlen($text);

			foreach ($names as $index => $name) {
				if ($arrOffset[$name] < $offset) {
					$delta = 0;
					if ($arrOffset[$name] === -2) {
						do {
							$delta++;
						} while (isset($text[$offset + $delta]) && $text[$offset + $delta] >= "\x80" && $text[$offset + $delta] < "\xC0");
					}

					if ($offset + $delta > strlen($text)) {
						unset($names[$index]);
						continue;

					} elseif ($arrMatches[$name] = Regexp::match(
							$text,
							$pl[$name]['pattern'],
							Regexp::OFFSET_CAPTURE,
							$offset + $delta)
					) {
						$m = &$arrMatches[$name];
						if (!strlen($m[0][0])) {
							continue;
						}
						$arrOffset[$name] = $m[0][1];
						foreach ($m as $keyx => $value) {
							$m[$keyx] = $value[0];
						}

					} else {
						// try next time?
						if (!$pl[$name]['again'] || !Regexp::match($text, $pl[$name]['again'], null, $offset + $delta)) {
							unset($names[$index]);
						}
						continue;
					}
				} // if

				if ($arrOffset[$name] < $minOffset) {
					$minOffset = $arrOffset[$name];
					$min = $name;
				}
			} // foreach

			if ($min === null) {
				break;
			}

			$px = $pl[$min];
			$offset = $start = $arrOffset[$min];

			$this->again = false;
			$res = call_user_func_array(
				$px['handler'],
				[$this, $arrMatches[$min], $min]
			);

			if ($res instanceof HtmlElement) {
				$res = $res->toString($this->texy);
			} elseif ($res === false) {
				$arrOffset[$min] = -2;
				continue;
			}

			$len = strlen($arrMatches[$min][0]);
			$text = substr_replace(
				$text,
				(string) $res,
				$start,
				$len
			);

			$delta = strlen($res) - $len;
			foreach ($names as $name) {
				if ($arrOffset[$name] < $start + $len) {
					$arrOffset[$name] = -1;
				} else {
					$arrOffset[$name] += $delta;
				}
			}

			if ($this->again) {
				$arrOffset[$min] = -2;
			} else {
				$arrOffset[$min] = -1;
				$offset += strlen($res);
			}
		} while (1);

		$this->element->insert(null, $text);
	}
}
