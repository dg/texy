<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


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
	// if succesfull, increments current position and returns true
	public function next($pattern, &$matches)
	{
		if ($this->offset > strlen($this->text)) {
			return false;
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
			return true;
		}
	}


	public function moveBackward($linesCount = 1)
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


	/**
	 * @param  string
	 * @return void
	 */
	public function parse($text)
	{
		$this->texy->invokeHandlers('beforeBlockParse', [$this, &$text]);

		// parser initialization
		$this->text = $text;
		$this->offset = 0;

		// parse loop
		$matches = [];
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
				$matches[] = [$offset, $name, $m, $priority];
			}
			$priority++;
		}
		unset($name, $pattern, $ms, $m, $k, $v);

		usort($matches, function ($a, $b) {
			if ($a[0] === $b[0]) {
				return $a[3] < $b[3] ? -1 : 1;
			}
			if ($a[0] < $b[0]) {
				return -1;
			}
			return 1;
		});
		$matches[] = [strlen($text), null, null]; // terminal cap


		// process loop
		$el = $this->element;
		$cursor = 0;
		do {
			do {
				list($mOffset, $mName, $mMatches) = $matches[$cursor];
				$cursor++;
				if ($mName === null || $mOffset >= $this->offset) {
					break;
				}
			} while (1);

			// between-matches content
			if ($mOffset > $this->offset) {
				$s = trim(substr($text, $this->offset, $mOffset - $this->offset));
				if ($s !== '') {
					$this->texy->paragraphModule->process($this, $s, $el);
				}
			}

			if ($mName === null) {
				break; // finito
			}

			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			$res = call_user_func_array(
				$this->patterns[$mName]['handler'],
				[$this, $mMatches, $mName]
			);

			if ($res === false || $this->offset <= $mOffset) { // module rejects text
				// asi by se nemelo stat, rozdeli generic block
				$this->offset = $mOffset; // turn offset back
				continue;

			} elseif ($res instanceof HtmlElement) {
				$el->insert(null, $res);

			} elseif (is_string($res)) {
				$el->insert(null, $res);
			}
		} while (1);
	}
}
