<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


class Regexp
{
	public const ALL = 1;
	public const OFFSET_CAPTURE = 2;


	/**
	 * Splits string by a regular expression.
	 * @param  int $flags  OFFSET_CAPTURE
	 */
	public static function split(string $subject, string $pattern, int $flags = 0): array
	{
		$reFlags = (($flags & self::OFFSET_CAPTURE) ? PREG_SPLIT_OFFSET_CAPTURE : 0) | PREG_SPLIT_DELIM_CAPTURE;
		$res = preg_split($pattern, $subject, -1, $reFlags);
		if (preg_last_error()) { // run-time error
			trigger_error(preg_last_error_msg(), E_USER_WARNING);
		}

		return $res;
	}


	/**
	 * Performs a regular expression match.
	 * @param  int $flags  OFFSET_CAPTURE, ALL
	 */
	public static function match(string $subject, string $pattern, int $flags = 0, int $offset = 0): mixed
	{
		$empty = $flags & self::ALL ? [] : null;
		if ($offset > strlen($subject)) {
			return $empty;
		}

		$reFlags = ($flags & self::OFFSET_CAPTURE) ? PREG_OFFSET_CAPTURE : 0;
		$res = $flags & self::ALL
			? preg_match_all($pattern, $subject, $m, $reFlags | PREG_SET_ORDER, $offset)
			: preg_match($pattern, $subject, $m, $reFlags, $offset);
		if (preg_last_error()) { // run-time error
			trigger_error(preg_last_error_msg(), E_USER_WARNING);
		} elseif ($res) {
			return $m;
		}

		return $empty;
	}


	/**
	 * Perform a regular expression search and replace.
	 */
	public static function replace(
		string $subject,
		string|array $pattern,
		string|callable|null $replacement = null,
	): string
	{
		if (is_object($replacement) || is_array($replacement)) {
			$res = preg_replace_callback($pattern, $replacement, $subject);
			if ($res === null && preg_last_error()) { // run-time error
				trigger_error(preg_last_error_msg(), E_USER_WARNING);
			}

			return $res;

		} elseif ($replacement === null && is_array($pattern)) {
			$replacement = array_values($pattern);
			$pattern = array_keys($pattern);
		}

		$res = preg_replace($pattern, $replacement, $subject);
		if (preg_last_error()) { // run-time error
			trigger_error(preg_last_error_msg(), E_USER_WARNING);
		}

		return $res;
	}
}
