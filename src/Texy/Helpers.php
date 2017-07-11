<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Helpers.
 */
final class Helpers
{
	use Strict;

	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . get_class($this));
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
		$s = Regexp::replace($s, '#[\x00-\x08\x0B-\x1F]+#', '');

		// right trim
		$s = Regexp::replace($s, "#[\t ]+$#m", '');

		// trailing spaces
		$s = trim($s, "\n");

		return $s;
	}


	/**
	 * Converts UTF-8 to ASCII.
	 * iconv('UTF-8', 'ASCII//TRANSLIT', ...) has problem with glibc!
	 */
	public static function toAscii($s)
	{
		$s = strtr($s, '`\'"^~', '-----');
		if (ICONV_IMPL === 'glibc') {
			$s = @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT', $s); // intentionally @
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2"
				. "\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe",
				'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt');
		} else {
			$s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s); // intentionally @
		}
		$s = str_replace(['`', "'", '"', '^', '~'], '', $s);
		return $s;
	}


	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 * @param  string
	 * @param  string
	 * @return string
	 */
	public static function webalize($s, $charlist = null)
	{
		$s = self::toAscii($s);
		$s = strtolower($s);
		$s = Regexp::replace($s, '#[^a-z0-9' . preg_quote($charlist, '#') . ']+#', '-');
		$s = trim($s, '-');
		return $s;
	}


	/**
	 * Outdents text block.
	 * @param  string
	 * @return string
	 */
	public static function outdent($s, $firstLine = false)
	{
		$s = trim($s, "\n");
		if ($firstLine) {
			$min = strspn($s, ' ');
		} else {
			$min = strlen($s);
			foreach (Regexp::match($s, '#^ *\S#m', Regexp::ALL) as $m) {
				$min = min($min, strlen($m[0]) - 1);
			}
		}
		if ($min) {
			$s = Regexp::replace($s, "#^ {1,$min}#m", '');
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
		if ($root == null || !self::isRelative($URL)) {
			return $URL;
		}
		return rtrim($root, '/\\') . '/' . $URL;
	}
}
