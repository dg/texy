<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Helpers.
 */
final class TexyHelpers
{
	use TexyStrict;

	public function __construct()
	{
		throw new LogicException('Cannot instantiate static class ' . get_class($this));
	}


	/**
	 * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04.
	 * which are ignored by TexyHtmlOutputModule routine
	 * @param  string
	 * @return string
	 */
	public static function freezeSpaces($s)
	{
		return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
	}


	/**
	 * Reverts meta-spaces back to normal spaces.
	 * @param  string
	 * @return string
	 */
	public static function unfreezeSpaces($s)
	{
		return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
	}


	/**
	 * Removes special controls characters and normalizes line endings and spaces.
	 * @param  string
	 * @return string
	 */
	public static function normalize($s)
	{
		// standardize line endings to unix-like
		$s = str_replace("\r\n", "\n", $s); // DOS
		$s = strtr($s, "\r", "\n"); // Mac

		// remove special chars; leave \t + \n
		$s = TexyRegexp::replace($s, '#[\x00-\x08\x0B-\x1F]+#', '');

		// right trim
		$s = TexyRegexp::replace($s, "#[\t ]+$#m", '');

		// trailing spaces
		$s = trim($s, "\n");

		return $s;
	}


	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 * @param  string
	 * @param  string
	 * @return string
	 */
	public static function webalize($s, $charlist = NULL)
	{
		$s = TexyUtf::utf2ascii($s);
		$s = strtolower($s);
		$s = TexyRegexp::replace($s, '#[^a-z0-9'.preg_quote($charlist, '#').']+#', '-');
		$s = trim($s, '-');
		return $s;
	}


	/**
	 * Outdents text block.
	 * @param  string
	 * @return string
	 */
	public static function outdent($s)
	{
		$s = trim($s, "\n");
		$min = strlen($s);
		foreach (TexyRegexp::match($s, '#^ *\S#m', TexyRegexp::ALL) as $m) {
			$min = min($min, strlen($m[0]) - 1);
		}
		if ($min) {
			$s = TexyRegexp::replace($s, "#^ {{$min}}#m", '');
		}
		return $s;
	}


	/**
	 * Is given URL relative?
	 * @param  string  URL
	 * @return bool
	 */
	public static function isRelative($URL)
	{
		// check for scheme, or absolute path, or absolute URL
		return !preg_match('#[a-z][a-z0-9+.-]{0,20}:|[\#/?]#Ai', $URL);
	}


	/**
	 * Prepends root to URL, if possible.
	 * @param  string  URL
	 * @param  string  root
	 * @return string
	 */
	public static function prependRoot($URL, $root)
	{
		if ($root == NULL || !self::isRelative($URL)) {
			return $URL;
		}
		return rtrim($root, '/\\') . '/' . $URL;
	}

}
