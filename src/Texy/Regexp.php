<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use JetBrains\PhpStorm\Language;
use function array_keys, array_values, in_array, is_array, is_string, preg_last_error, preg_last_error_msg, strlen;
use const PREG_OFFSET_CAPTURE, PREG_SET_ORDER, PREG_SPLIT_DELIM_CAPTURE, PREG_SPLIT_NO_EMPTY, PREG_SPLIT_OFFSET_CAPTURE;


/**
 * Regular expression utilities with error handling.
 */
class Regexp
{
	/**
	 * Splits string by a regular expression. Subpatterns in parentheses will be captured and returned as well.
	 * @return ($captureOffset is true ? list<array{string, int}> : list<string>)
	 */
	public static function split(
		string $subject,
		#[Language('RegExp')]
		string $pattern,
		bool $captureOffset = false,
		bool $skipEmpty = false,
		int $limit = -1,
	): array
	{
		$flags = ($captureOffset ? PREG_SPLIT_OFFSET_CAPTURE : 0) | ($skipEmpty ? PREG_SPLIT_NO_EMPTY : 0);
		return self::pcre('preg_split', [$pattern, $subject, $limit, $flags | PREG_SPLIT_DELIM_CAPTURE]);
	}


	/**
	 * Searches the string for the part matching the regular expression and returns
	 * an array with the found expression and individual subexpressions, or null.
	 * @return ($captureOffset is true ? array<int|string, array{string, int}> : array<int|string, string>)|null
	 */
	public static function match(
		string $subject,
		#[Language('RegExp')]
		string $pattern,
		bool $captureOffset = false,
		int $offset = 0,
	): ?array
	{
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0);
		if ($offset > strlen($subject)) {
			return null;
		}

		$m = [];
		return self::pcre('preg_match', [$pattern, $subject, &$m, $flags, $offset])
			? $m
			: null;
	}


	/**
	 * Searches the string for all occurrences matching the regular expression and
	 * returns an array of arrays containing the found expression and each subexpression.
	 * @return ($captureOffset is true ? list<array<int|string, array{string, int}>> : list<array<int|string, string>>)
	 */
	public static function matchAll(
		string $subject,
		#[Language('RegExp')]
		string $pattern,
		bool $captureOffset = false,
		int $offset = 0,
	): array
	{
		if ($offset > strlen($subject)) {
			return [];
		}

		$m = [];
		$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0) | PREG_SET_ORDER;
		self::pcre('preg_match_all', [$pattern, $subject, &$m, $flags, $offset]);
		return $m;
	}


	/**
	 * Replaces all occurrences matching regular expression $pattern which can be string or array in the form `pattern => replacement`.
	 * @param  string|string[]  $pattern
	 * @param  string|\Closure(string[]): string  $replacement
	 */
	public static function replace(
		string $subject,
		#[Language('RegExp')]
		string|array $pattern,
		string|\Closure $replacement = '',
		int $limit = -1,
		bool $captureOffset = false,
	): string
	{
		if ($replacement instanceof \Closure) {
			$flags = ($captureOffset ? PREG_OFFSET_CAPTURE : 0);
			return self::pcre('preg_replace_callback', [$pattern, $replacement, $subject, $limit, 0, $flags]);

		} elseif (is_array($pattern) && is_string(key($pattern))) {
			return self::pcre('preg_replace', [array_keys($pattern), array_values($pattern), $subject, $limit]);

		} else {
			return self::pcre('preg_replace', [$pattern, $replacement, $subject, $limit]);
		}
	}


	public static function quote(string $s): string
	{
		return preg_quote($s, '#');
	}


	/**
	 * @internal
	 * @param  array<mixed>  $args
	 */
	public static function pcre(string $func, array $args): mixed
	{
		assert(is_callable($func));
		$res = @$func(...$args);
		if (($code = preg_last_error()) // run-time error, but preg_last_error & return code are liars
			&& ($res === null || !in_array($func, ['preg_replace_callback', 'preg_replace'], true))
		) {
			throw new RegexpException(preg_last_error_msg() . ' (pattern: ' . implode(' or ', (array) $args[0]) . ')', $code);
		}

		return $res;
	}
}
