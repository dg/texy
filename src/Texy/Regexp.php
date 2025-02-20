<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use JetBrains\PhpStorm\Language;


class Regexp
{
	/**
	 * Divides the string into arrays according to the regular expression. Expressions in parentheses will be captured and returned as well.
	 */
	public static function split(
		string $subject,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		bool $captureOffset = false,
		bool $skipEmpty = false,
		int $limit = -1,
	): array
	{
		$flags = ($captureOffset ? PREG_SPLIT_OFFSET_CAPTURE : 0) | ($skipEmpty ? PREG_SPLIT_NO_EMPTY : 0);
		return self::pcre('preg_split', [$pattern . 'ux', $subject, $limit, $flags | PREG_SPLIT_DELIM_CAPTURE]);
	}


	/**
	 * Searches the string for the part matching the regular expression and returns
	 * an array with the found expression and individual subexpressions, or `null`.
	 */
	public static function match(
		string $subject,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		bool $captureOffset = false,
		int $offset = 0,
	): ?array
	{
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0) | PREG_UNMATCHED_AS_NULL;
		if ($offset > strlen($subject)) {
			return null;
		} elseif (!self::pcre('preg_match', [$pattern . 'ux', $subject, &$m, $flags, $offset])) {
			return null;
		} else {
			return $m;
		}
	}


	/**
	 * Searches the string for all occurrences matching the regular expression and
	 * returns an array of arrays containing the found expression and each subexpression.
	 * @return array[]
	 */
	public static function matchAll(
		string $subject,
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		bool $captureOffset = false,
		int $offset = 0,
	): array
	{
		if ($offset > strlen($subject)) {
			return [];
		}
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0) | PREG_UNMATCHED_AS_NULL | PREG_SET_ORDER;
		self::pcre('preg_match_all', [$pattern . 'ux', $subject, &$m, $flags, $offset]);
		return $m;
	}


	/**
	 * Replaces all occurrences matching regular expression $pattern which can be string or array in the form `pattern => replacement`.
	 */
	public static function replace(
		string $subject,
		#[Language('PhpRegExpXTCommentMode')]
		string|array $pattern,
		string|callable $replacement = '',
		int $limit = -1,
		bool $captureOffset = false,
	): string
	{
		if (is_object($replacement) || is_array($replacement)) {
			if (!is_callable($replacement, false, $textual)) {
				throw new Exception("Callback '$textual' is not callable.");
			}

			$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0) | PREG_UNMATCHED_AS_NULL;
			return self::pcre('preg_replace_callback', [$pattern . 'ux', $replacement, $subject, $limit, 0, $flags]);

		} elseif (is_array($pattern) && is_string(key($pattern))) {
			$patterns = array_map(static fn($p) => $p . 'ux', array_keys($pattern));
			return self::pcre('preg_replace', [$patterns, array_values($pattern), $subject, $limit]);

		} else {
			return self::pcre('preg_replace', [$pattern . 'ux', $replacement, $subject, $limit]);
		}
	}


	public static function quote(string $s): string
	{
		return addcslashes($s, "\x00..\x20-.\\+*?[^]$(){}=!<>|:-#");
	}


	/** @internal */
	public static function pcre(string $func, array $args)
	{
		$res = @$func(...$args);
		if (($code = preg_last_error()) // run-time error, but preg_last_error & return code are liars
			&& ($res === null || !in_array($func, ['preg_replace_callback', 'preg_replace'], true))
		) {
			throw new RegexpException(preg_last_error_msg() . ' (pattern: ' . implode(' or ', (array) $args[0]) . ')', $code);
		}

		return $res;
	}
}
