<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy parser base class.
 *
 * @author     David Grudl
 */
class Parser extends Object
{
	/** @var Texy */
	protected $texy;

	/** @var HtmlElement */
	protected $element;

	/** @var array */
	public $patterns;


	/**
	 * @return Texy
	 */
	public function getTexy()
	{
		return $this->texy;
	}

}


/**
 * Parser for block structures.
 */
class BlockParser extends Parser
{
	/** @var string */
	private $text;

	/** @var int */
	private $offset;

	/** @var bool */
	private $indented;


	/**
	 * @param  Texy
	 * @param  HtmlElement
	 */
	public function __construct(Texy $texy, HtmlElement $element, $indented)
	{
		$this->texy = $texy;
		$this->element = $element;
		$this->indented = (bool) $indented;
		$this->patterns = $texy->getBlockPatterns();
	}


	public function isIndented()
	{
		return $this->indented;
	}


	// match current line against RE.
	// if succesfull, increments current position and returns TRUE
	public function next($pattern, & $matches)
	{
		if ($this->offset > strlen($this->text)) {
			return FALSE;
		}
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
			return TRUE;
		}
	}


	public function moveBackward($linesCount = 1)
	{
		while (--$this->offset > 0) {
			if ($this->text{ $this->offset-1 } === "\n") {
				$linesCount--;
				if ($linesCount < 1) {
					break;
				}
			}
		}

		$this->offset = max($this->offset, 0);
	}


	public static function cmp($a, $b)
	{
		if ($a[0] === $b[0]) {
			return $a[3] < $b[3] ? -1 : 1;
		}
		if ($a[0] < $b[0]) {
			return -1;
		}
		return 1;
	}


	/**
	 * @param  string
	 * @return void
	 */
	public function parse($text)
	{
		$tx = $this->texy;

		$tx->invokeHandlers('beforeBlockParse', array($this, & $text));

		// parser initialization
		$this->text = $text;
		$this->offset = 0;

		// parse loop
		$matches = array();
		$priority = 0;
		foreach ($this->patterns as $name => $pattern) {
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
				$matches[] = array($offset, $name, $m, $priority);
			}
			$priority++;
		}
		unset($name, $pattern, $ms, $m, $k, $v);

		usort($matches, array(__CLASS__, 'cmp')); // generates strict error in PHP 5.1.2
		$matches[] = array(strlen($text), NULL, NULL); // terminal cap


		// process loop
		$el = $this->element;
		$cursor = 0;
		do {
			do {
				list($mOffset, $mName, $mMatches) = $matches[$cursor];
				$cursor++;
				if ($mName === NULL || $mOffset >= $this->offset) {
					break;
				}
			} while (1);

			// between-matches content
			if ($mOffset > $this->offset) {
				$s = trim(substr($text, $this->offset, $mOffset - $this->offset));
				if ($s !== '') {
					$tx->paragraphModule->process($this, $s, $el);
				}
			}

			if ($mName === NULL) {
				break; // finito
			}

			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			$res = call_user_func_array(
				$this->patterns[$mName]['handler'],
				array($this, $mMatches, $mName)
			);

			if ($res === FALSE || $this->offset <= $mOffset) { // module rejects text
				// asi by se nemelo stat, rozdeli generic block
				$this->offset = $mOffset; // turn offset back
				continue;

			} elseif ($res instanceof HtmlElement) {
				$el->insert(NULL, $res);

			} elseif (is_string($res)) {
				$el->insert(NULL, $res);
			}

		} while (1);
	}

}


/**
 * Parser for single line structures.
 */
class LineParser extends Parser
{
	/** @var bool */
	public $again;


	/**
	 * @param  Texy
	 * @param  HtmlElement
	 */
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

					} elseif ($arrMatches[$name] = Regexp::match(
							$text,
							$pl[$name]['pattern'],
							Regexp::OFFSET_CAPTURE,
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
						if (!$pl[$name]['again'] || !Regexp::match($text, $pl[$name]['again'], NULL, $offset + $delta)) {
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

			if ($res instanceof HtmlElement) {
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
