<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function defined, extension_loaded;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Static utility helpers.
 */
final class Helpers
{
	/** Matches one HTML entity reference; shared by languages that treat `&...;` as an entity. */
	public const EntityPattern = '&(?:\#[0-9]{1,7}|\#[xX][0-9a-fA-F]{1,6}|[a-zA-Z][a-zA-Z0-9]{1,31});';


	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * StrToLower in UTF-8.
	 */
	public static function toLower(string $s): string
	{
		return mb_strtolower($s, 'UTF-8');
	}


	public static function unescapeHtml(string $s): string
	{
		return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}


	/**
	 * Decodes HTML entities into display text; strips control characters
	 * they may smuggle in (input normalization does the same for raw input).
	 */
	public static function decodeEntities(string $s): string
	{
		if (!str_contains($s, '&')) {
			return $s;
		}

		return self::stripControlChars(self::unescapeHtml($s));
	}


	/**
	 * Decodes a single reference matched by EntityPattern into display text,
	 * null when it is not a known entity and should stay literal.
	 */
	public static function decodeEntity(string $ref): ?string
	{
		$s = self::unescapeHtml($ref);
		if ($s === $ref) {
			// html_entity_decode refuses NUL and other control references; per CommonMark
			// only NUL maps to U+FFFD, the rest stay literal (a deliberate deviation:
			// the reference implementation decodes them into raw control characters)
			return preg_match('~^&\#(?:0+|[xX]0+);$~', $ref) ? "\u{FFFD}" : null;
		}

		return self::stripControlChars($s);
	}


	/**
	 * Strips control characters that must never occur in AST text (they collide with
	 * the internal marker bytes); every decoder that can create them must call this.
	 * \x0D survives - it is the internal hard line break marker.
	 */
	public static function stripControlChars(string $s): string
	{
		return Regexp::replace($s, '~[\x00-\x08\x0B\x0C\x0E-\x1F]+~');
	}


	/**
	 * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04.
	 * which are ignored by the well-forming engine
	 */
	public static function freezeSpaces(string $s): string
	{
		return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
	}


	/**
	 * Reverts meta-spaces back to normal spaces.
	 */
	public static function unfreezeSpaces(string $s): string
	{
		return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
	}


	/**
	 * Converts UTF-8 to ASCII, ie removes diacritics etc. Requires the intl extension.
	 */
	public static function toAscii(string $s): string
	{
		if (!extension_loaded('intl')) {
			throw new \LogicException(__METHOD__ . '() requires INTL extension that is not loaded.');
		}

		$iconv = defined('ICONV_IMPL') ? trim(ICONV_IMPL, '"\'') : null;

		// remove control characters
		$s = Regexp::replace($s, '#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#');

		// transliteration (by Transliterator and iconv) is not optimal, replace some characters directly
		$s = strtr($s, [
			"\u{201E}" => '"', "\u{201C}" => '"', "\u{201D}" => '"', "\u{201A}" => "'", "\u{2018}" => "'", "\u{2019}" => "'",
			"\u{B0}" => '^', "\u{42F}" => 'Ya', "\u{44F}" => 'ya', "\u{42E}" => 'Yu', "\u{44E}" => 'yu', "\u{c4}" => 'Ae', "\u{d6}" => 'Oe',
			"\u{dc}" => 'Ue', "\u{1e9e}" => 'Ss', "\u{e4}" => 'ae', "\u{f6}" => 'oe', "\u{fc}" => 'ue', "\u{df}" => 'ss',
		]); // „ “ ” ‚ ‘ ’ ° Я я Ю ю Ä Ö Ü ẞ ä ö ü ß
		if ($iconv !== 'libiconv') {
			$s = strtr($s, [
				"\u{AE}" => '(R)', "\u{A9}" => '(c)', "\u{2026}" => '...', "\u{AB}" => '<<', "\u{BB}" => '>>', "\u{A3}" => 'lb',
				"\u{A5}" => 'yen', "\u{B2}" => '^2', "\u{B3}" => '^3', "\u{B5}" => 'u', "\u{B9}" => '^1', "\u{BA}" => 'o', "\u{BF}" => '?',
				"\u{2CA}" => "'", "\u{2CD}" => '_', "\u{2DD}" => '"', "\u{1FEF}" => '', "\u{20AC}" => 'EUR', "\u{2122}" => 'TM',
				"\u{212E}" => 'e', "\u{2190}" => '<-', "\u{2191}" => '^', "\u{2192}" => '->', "\u{2193}" => 'V', "\u{2194}" => '<->',
			]); // ® © … « » £ ¥ ² ³ µ ¹ º ¿ ˊ ˍ ˝ ` € ™ ℮ ← ↑ → ↓ ↔
		}

		$s = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($s)
			?? throw new Exception('Transliterator::transliterate() failed.');

		// use iconv because the transliterator leaves some characters out of ASCII, eg → ʾ
		if ($iconv === 'glibc') {
			$s = strtr($s, '?', "\x01"); // temporarily hide ? to distinguish them from the garbage that iconv creates
			$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
			if ($s === false) {
				throw new Exception('iconv() failed.');
			}

			$s = str_replace(['?', "\x01"], ['', '?'], $s); // remove garbage and restore ? characters

		} elseif ($iconv === 'libiconv') {
			$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
			if ($s === false) {
				throw new Exception('iconv() failed.');
			}
		} else { // null or 'unknown'
			$s = Regexp::replace($s, '#[^\x00-\x7F]++#'); // remove non-ascii chars
		}

		return $s;
	}


	/**
	 * Converts to web safe characters [a-z0-9-] text. Invisible format characters are ignored.
	 */
	public static function webalize(string $s, string $charlist = ''): string
	{
		$s = self::toAscii(Regexp::replace($s, '~\p{Cf}+~u'));
		$s = strtolower($s);
		$s = Regexp::replace($s, '~[^a-z0-9' . Regexp::quote($charlist) . ']+~', '-');
		$s = trim($s, '-');
		return $s;
	}


	/**
	 * Outdents text block: strips the whitespace prefix shared by all lines
	 * (or taken from the first line). Line endings of any kind become \n.
	 */
	public static function outdent(string $s, bool $firstLine = false): string
	{
		$s = strtr(str_replace("\r\n", "\n", $s), "\r", "\n");
		return Parsing\OffsetMap::outdentLines(Parsing\OffsetMap::linesOf(trim($s, "\n"), 0), $firstLine)[0];
	}


	/**
	 * Is given URL relative?
	 */
	public static function isRelative(string $URL): bool
	{
		// check for scheme, or absolute path, or absolute URL
		return !Regexp::match($URL, '~[a-z][a-z0-9+.-]{0,20}:|[#/?]~Ai');
	}


	/**
	 * Prepends root to URL, if possible.
	 */
	public static function prependRoot(string $URL, ?string $root): string
	{
		if ($root == null || !self::isRelative($URL)) { // intentionally ==, skips null and empty string
			return $URL;
		}

		return rtrim($root, '/\\') . '/' . $URL;
	}


	/**
	 * Prepends http:// to scheme-less www.* URLs.
	 */
	public static function normalizeWww(string $url): string
	{
		return strncasecmp($url, 'www.', 4) === 0
			? 'http://' . $url
			: $url;
	}


	/**
	 * Returns shortened URL for display (e.g., in autolinks).
	 */
	public static function shortenUrl(string $url): string
	{
		$raw = strncasecmp($url, 'www.', 4) === 0
			? 'none://' . $url
			: $url;

		// parse_url() in PHP damages UTF-8 - use regular expression
		if (!preg_match('~^
			(?: (?P<scheme> [a-z]+ ) : )?
			(?: // (?P<host> [^/?#]+ ) )?
			(?P<path> (?: / | ^ ) (?! / ) [^?#]* )?
			(?: \? (?P<query> [^#]* ) )?
			(?: \# (?P<fragment> .* ) )?
			$
		~x', $raw, $parts)) {
			return $url;
		}

		$res = '';
		if (($parts['scheme'] ?? '') !== '' && $parts['scheme'] !== 'none') {
			$res .= $parts['scheme'] . '://';
		}

		if (($parts['host'] ?? '') !== '') {
			$res .= $parts['host'];
		}

		if (($parts['path'] ?? '') !== '') {
			$res .= mb_strlen($parts['path']) > 16
				? "/\u{2026}" . mb_substr($parts['path'], -12)
				: $parts['path'];
		}

		if (($parts['query'] ?? '') !== '') {
			$res .= mb_strlen($parts['query']) > 4
				? "?\u{2026}"
				: '?' . $parts['query'];
		} elseif (($parts['fragment'] ?? '') !== '') {
			$res .= mb_strlen($parts['fragment']) > 4
				? "#\u{2026}"
				: '#' . $parts['fragment'];
		}

		return $res;
	}
}
