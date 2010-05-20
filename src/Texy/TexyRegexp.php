<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */


class TexyRegexp
{
	const ALL = 1;
	const OFFSET_CAPTURE = 2;


	/**
	 * Splits string by a regular expression.
	 * @param  string
	 * @param  string
	 * @param  int  OFFSET_CAPTURE
	 * @return array
	 */
	public static function split($subject, $pattern, $flags = 0)
	{
		/*set_error_handler(function($severity, $message) use ($pattern) { // preg_last_error does not return compile errors
			restore_error_handler();
			throw new TexyRegexpException("$message in pattern: $pattern");
		});*/
		$reFlags = (($flags & self::OFFSET_CAPTURE) ? PREG_SPLIT_OFFSET_CAPTURE : 0) | PREG_SPLIT_DELIM_CAPTURE;
		$res = preg_split($pattern, $subject, -1, $reFlags);
		//restore_error_handler();
		if (preg_last_error()) { // run-time error
			throw new TexyRegexpException(NULL, preg_last_error(), $pattern);
		}
		return $res;
	}


	/**
	 * Performs a regular expression match.
	 * @param  string
	 * @param  string
	 * @param  int  OFFSET_CAPTURE, ALL
	 * @param  int  offset in bytes
	 * @return mixed
	 */
	public static function match($subject, $pattern, $flags = 0, $offset = 0)
	{
		$empty = $flags & self::ALL ? array() : NULL;
		if ($offset > strlen($subject)) {
			return $empty;
		}
		/*set_error_handler(function($severity, $message) use ($pattern) { // preg_last_error does not return compile errors
			restore_error_handler();
			throw new TexyRegexpException("$message in pattern: $pattern");
		});*/
		$reFlags = ($flags & self::OFFSET_CAPTURE) ? PREG_OFFSET_CAPTURE : 0;
		if ($flags & self::ALL) {
			$res = preg_match_all($pattern, $subject, $m, $reFlags | PREG_SET_ORDER, $offset);
		} else {
			$res = preg_match($pattern, $subject, $m, $reFlags, $offset);
		}
		//restore_error_handler();
		if (preg_last_error()) { // run-time error
			throw new TexyRegexpException(NULL, preg_last_error(), $pattern);
		} elseif ($res) {
			return $m;
		}
		return $empty;
	}


	/**
	 * Perform a regular expression search and replace.
	 * @param  string
	 * @param  string|array
	 * @param  string|callable
	 * @return string
	 */
	public static function replace($subject, $pattern, $replacement = NULL)
	{
		if (is_object($replacement) || is_array($replacement)) {
			/*set_error_handler(function($severity, $message) use (& $tmp) { // preg_last_error does not return compile errors
				restore_error_handler();
				throw new RegexpException("$message in pattern: $tmp");
			});
			foreach ((array) $pattern as $tmp) {
				preg_match($tmp, '');
			}
			restore_error_handler();*/

			$res = preg_replace_callback($pattern, $replacement, $subject);
			if ($res === NULL && preg_last_error()) { // run-time error
				throw new TexyRegexpException(NULL, preg_last_error(), $pattern);
			}
			return $res;

		} elseif ($replacement === NULL && is_array($pattern)) {
			$replacement = array_values($pattern);
			$pattern = array_keys($pattern);
		}

		/*set_error_handler(function($severity, $message) use ($pattern) { // preg_last_error does not return compile errors
			restore_error_handler();
			throw new TexyRegexpException("$message in pattern: " . implode(' or ', (array) $pattern));
		});*/
		$res = preg_replace($pattern, $replacement, $subject);
		//restore_error_handler();
		if (preg_last_error()) { // run-time error
			throw new TexyRegexpException(NULL, preg_last_error(), implode(' or ', (array) $pattern));
		}
		return $res;
	}

}
