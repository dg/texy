<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */


class TexyRegexp
{
	const ALL = 1;
	const OFFSET_CAPTURE = 2;

	private static $messages = array(
		PREG_INTERNAL_ERROR => 'Internal error',
		PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted',
		PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted',
		PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
		5 => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point', // PREG_BAD_UTF8_OFFSET_ERROR
	);


	/**
	 * Splits string by a regular expression.
	 * @param  string
	 * @param  string
	 * @param  int  OFFSET_CAPTURE
	 * @return array
	 */
	public static function split($subject, $pattern, $flags = 0)
	{
		$reFlags = (($flags & self::OFFSET_CAPTURE) ? PREG_SPLIT_OFFSET_CAPTURE : 0) | PREG_SPLIT_DELIM_CAPTURE;
		$res = preg_split($pattern, $subject, -1, $reFlags);
		if (preg_last_error()) { // run-time error
			trigger_error(@self::$messages[preg_last_error()], E_USER_WARNING);
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
		if ($offset > strlen($subject)) {
			return NULL;
		}
		$reFlags = ($flags & self::OFFSET_CAPTURE) ? PREG_OFFSET_CAPTURE : 0;
		if ($flags & self::ALL) {
			$res = preg_match_all($pattern, $subject, $m, $reFlags | PREG_SET_ORDER, $offset);
		} else {
			$res = preg_match($pattern, $subject, $m, $reFlags, $offset);
		}
		if (preg_last_error()) { // run-time error
			trigger_error(@self::$messages[preg_last_error()], E_USER_WARNING);
		} elseif ($res) {
			return $m;
		}
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
			$res = preg_replace_callback($pattern, $replacement, $subject);
			if ($res === NULL && preg_last_error()) { // run-time error
				trigger_error(@self::$messages[preg_last_error()], E_USER_WARNING);
			}
			return $res;

		} elseif ($replacement === NULL && is_array($pattern)) {
			$replacement = array_values($pattern);
			$pattern = array_keys($pattern);
		}

		$res = preg_replace($pattern, $replacement, $subject);
		if (preg_last_error()) { // run-time error
			trigger_error(@self::$messages[preg_last_error()], E_USER_WARNING);
		}
		return $res;
	}

}
