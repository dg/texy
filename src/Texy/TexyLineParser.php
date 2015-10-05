<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Parser for single line structures.
 */
class TexyLineParser extends TexyParser
{
	/** @var bool */
	public $again;


	/**
	 * @param  Texy
	 * @param  TexyHtml
	 */
	public function __construct(Texy $texy, TexyHtml $element)
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
		$tx = $this->texy;

		// initialization
		$pl = $this->patterns;
		if (!$pl) {
			// nothing to do
			$this->element->insert(NULL, $text);
			return;
		}

		$offset = 0;
		$names = array_keys($pl);
		$arrMatches = $arrOffset = array();
		foreach ($names as $name) {
			$arrOffset[$name] = -1;
		}


		// parse loop
		do {
			$min = NULL;
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

					} elseif ($arrMatches[$name] = TexyRegexp::match(
							$text,
							$pl[$name]['pattern'],
							TexyRegexp::OFFSET_CAPTURE,
							$offset + $delta)
					) {
						$m = & $arrMatches[$name];
						if (!strlen($m[0][0])) {
							continue;
						}
						$arrOffset[$name] = $m[0][1];
						foreach ($m as $keyx => $value) {
							$m[$keyx] = $value[0];
						}

					} else {
						// try next time?
						if (!$pl[$name]['again'] || !TexyRegexp::match($text, $pl[$name]['again'], NULL, $offset + $delta)) {
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

			if ($min === NULL) {
				break;
			}

			$px = $pl[$min];
			$offset = $start = $arrOffset[$min];

			$this->again = FALSE;
			$res = call_user_func_array(
				$px['handler'],
				array($this, $arrMatches[$min], $min)
			);

			if ($res instanceof TexyHtml) {
				$res = $res->toString($tx);
			} elseif ($res === FALSE) {
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

		$this->element->insert(NULL, $text);
	}

}
