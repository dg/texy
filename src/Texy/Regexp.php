<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


class Regexp
{
	use Strict;

	public const ALL = 1;
	public const OFFSET_CAPTURE = 2;

	private static $messages = [
		PREG_INTERNAL_ERROR => 'Internal error',
		PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted',
		PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted',
		PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
		5 => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point', // PREG_BAD_UTF8_OFFSET_ERROR
	];


	/**
	 * Splits string by a regular expression.
	 * @param  int $flags  OFFSET_CAPTURE
	 */
	public static function split(string $subject, string $pattern, int $flags = 0): array
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
	 * @param  int $flags  OFFSET_CAPTURE, ALL
	 * @param  int $offset  offset in bytes
	 * @return mixed
	 */
	public static function match(string $subject, string $pattern, int $flags = 0, int $offset = 0)
	{
		$empty = $flags & self::ALL ? [] : null;
		if ($offset > strlen($subject)) {
			return $empty;
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
		return $empty;
	}


	/**
	 * Perform a regular expression search and replace.
	 * @param  string|array $pattern
	 * @param  string|callable $replacement
	 */
	public static function replace(string $subject, $pattern, $replacement = null): string
	{
		if (is_object($replacement) || is_array($replacement)) {
			$res = preg_replace_callback($pattern, $replacement, $subject);
			if ($res === null && preg_last_error()) { // run-time error
				trigger_error(@self::$messages[preg_last_error()], E_USER_WARNING);
			}
			return $res;

		} elseif ($replacement === null && is_array($pattern)) {
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
