<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use Nette\Utils\Strings;
use function function_exists;
use const ENT_HTML5, ENT_QUOTES, ICONV_IMPL;


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
		return function_exists('mb_strtolower')
			? mb_strtolower($s, 'UTF-8')
			: $s;
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
	 * Converts UTF-8 to ASCII.
	 */
	public static function toAscii(string $s): string
	{
		if (class_exists(Strings::class)) {
			return Strings::toAscii($s);
		}
		$s = strtr($s, '`\'"^~', '-----');
		if (ICONV_IMPL === 'glibc') {
			$s = (string) @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT', $s); // @ untranslatable characters trigger a notice
			$s = strtr(
				$s,
				"\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2"
				. "\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe",
				'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt',
			);
		} else {
			$s = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $s); // @ untranslatable characters trigger a notice
		}

		$s = str_replace(['`', "'", '"', '^', '~'], '', $s);
		return $s;
	}


	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 */
	public static function webalize(string $s, string $charlist = ''): string
	{
		$s = self::toAscii($s);
		$s = strtolower($s);
		$s = Regexp::replace($s, '~[^a-z0-9' . Regexp::quote($charlist) . ']+~', '-');
		$s = trim($s, '-');
		return $s;
	}


	/**
	 * Outdents text block: strips the whitespace prefix shared by all lines
	 * (or taken from the first line).
	 */
	public static function outdent(string $s, bool $firstLine = false): string
	{
		return Parsing\OffsetMap::outdentLines(Parsing\OffsetMap::linesOf(trim($s, "\n"), 0), $firstLine)[0];
	}


	/**
	 * Removes soft hyphens. URL targets parsed from source text are cleaned with this:
	 * soft hyphens get into them by copy-pasting hyphenated text and would break the address.
	 */
	public static function removeSoftHyphens(string $s): string
	{
		return str_replace("\u{AD}", '', $s);
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


	/**
	 * Extracts plain text content from AST node(s).
	 * @param  Node|iterable<Node>  $node
	 */
	public static function extractText(Node|iterable $node): string
	{
		// RawTextNode is visible text too - it is the node code-like content is held in,
		// and it has no children to recurse into
		if ($node instanceof Nodes\TextNode || $node instanceof Nodes\RawTextNode) {
			return $node->text;
		}

		$text = '';
		$children = $node instanceof Node ? $node->getChildren() : $node;
		foreach ($children as $child) {
			$text .= self::extractText($child);
		}

		return $text;
	}
}
