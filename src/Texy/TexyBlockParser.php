<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Parser for block structures.
 */
class TexyBlockParser extends TexyParser
{
	/** @var string */
	private $text;

	/** @var int */
	private $offset;

	/** @var bool */
	private $indented;


	/**
	 * @param  Texy
	 * @param  TexyHtml
	 */
	public function __construct(Texy $texy, TexyHtml $element, $indented)
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
		$matches = TexyRegexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			TexyRegexp::OFFSET_CAPTURE,
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
			if ($this->text{ $this->offset - 1 } === "\n") {
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
			$ms = TexyRegexp::match(
				$text,
				$pattern['pattern'],
				TexyRegexp::OFFSET_CAPTURE | TexyRegexp::ALL
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

			} elseif ($res instanceof TexyHtml) {
				$el->insert(NULL, $res);

			} elseif (is_string($res)) {
				$el->insert(NULL, $res);
			}

		} while (1);
	}

}
