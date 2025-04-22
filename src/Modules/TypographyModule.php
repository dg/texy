<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy\{Config, Patterns, Regexp};


/**
 * Applies typographic corrections (quotes, dashes, ellipsis, non-breaking spaces).
 * @internal
 */
final class TypographyModule
{
	/**
	 * search => replacement; :ldq:/:rdq:/:lsq:/:rsq: are placeholders for locale quotes
	 * @var array<string, string>
	 */
	private array $replacements = [
		'~(?<! [.\x{2026}] ) \.{3,4} (?! [.\x{2026}] )~mx'  => "\u{2026}",                // ellipsis  ...
		'~(?<= [\d ] | ^ ) - (?= [\d ] | $)~x' /* .         */ => "\u{2013}",                // en dash 123-123
		'~(?<= [^!*+,/:;<=>@\\\_|-] ) -- (?= [^!*+,/:;<=>@\\\_|-] )~x' => "\u{2013}",   // en dash alphanum--alphanum
		'~,-~' /* .                               */ => ",\u{2013}",               // en dash ,-
		'~(?<! \d ) (\d{1,2}\.) \ (\d{1,2}\.) \ (\d\d)~x' => "\$1\u{A0}\$2\u{A0}\$3",      // date 23. 1. 1978
		'~(?<! \d ) (\d{1,2}\.) \ (\d{1,2}\.)~x' /* .  */ => "\$1\u{A0}\$2",            // date 23. 1.
		'~\ ---\ ~' /* .                          */ => "\u{A0}\u{2014} ",         // em dash ---
		'~\ ([\x{2013}\x{2014}])~' /* .           */ => "\u{A0}\$1",               // &nbsp; behind dash (dash stays at line end)
		'~\ <-{1,2} >\ ~x' /* .                      */ => " \u{2194} ",              // left right arrow <-->
		'~-{1,}>\ ~' /* .                          */ => "\u{2192} ",               // right arrow -->
		'~\ <-{1,}~' /* .                         */ => " \u{2190} ",              // left arrow <--
		'~={1,}>\ ~' /* .                         */ => "\u{21D2} ",              // right arrow ==>
		'~\+-~' /* .                              */ => "\u{B1}",                  // +-
		'~(\d++) \ x\ (?= \d)~x' /* .                */ => "\$1\u{A0}\u{D7}\u{A0}",   // dimension sign 123 x 123...
		'~(\d++) x (?= \d)~x' /* .                    */ => "\$1\u{D7}",               // dimension sign 123x123...
		'~(?<= \d ) x (?= \ |,|\.|$ )~mx' /* .           */ => "\u{D7}",                  // dimension sign 123x
		'~(\S\ ?) \(TM\)~ix' /* .                   */ => "\$1\u{2122}",             // trademark (TM)
		'~(\S\ ?) \(R\)~ix' /* .                    */ => "\$1\u{AE}",               // registered (R)
		'~\(C\) (\ ?\S)~ix' /* .                    */ => "\u{A9}\$1",               // copyright (C)
		'~\(EUR\)~' /* .                          */ => "\u{20AC}",                // Euro (EUR)
		'~(\d) \ (?= \d{3})~x' /* .                  */ => "\$1\u{A0}",               // (phone) number 1 123 123 123...

		// CONTENT_MARKUP mark: \x17-\x1F, CONTENT_REPLACED mark: \x16, CONTENT_TEXTUAL mark: \x17
		'~(?<= [^\s\x17] ) \s++ ([\x17-\x1F]++)(?= \s)~x'  => '$1',                      // remove intermarkup space phase 1
		'~(?<= \s) ([\x17-\x1F]++) \s++~x' /* .       */ => '$1',                      // remove intermarkup space phase 2

		'~(?<= .{50}) \s++ (?= [\x17-\x1F]* \S{1,6} [\x17-\x1F]* $)~sx' => "\u{A0}",      // space before last short word

		// nbsp space between number (optionally followed by dot) and word, symbol, punctation, currency symbol
		'~(?<= ^|\ |\.|,|-|\+|\x16|\(|\d\x{A0}) ([\x17-\x1F]* \d++ \.? [\x17-\x1F]*) \s++ (?= [\x17-\x1F]* [%'
		. Patterns::CHAR
		. '\x{b0}-\x{be}\x{2020}-\x{214f}])~mx' => "\$1\u{A0}",
		// space between preposition and word
		'~(?<= ^|[^0-9'
		. Patterns::CHAR
		. ']) ([\x17-\x1F]* [ksvzouiKSVZOUIA] [\x17-\x1F]*) \s++ (?= [\x17-\x1F]* [0-9'
		. Patterns::CHAR
		. '])~msx' => "\$1\u{A0}",

		// double ""
		'~(?<! "|\w) " (?! \ | " ) ( (?: [^"]++ | ")+ ) (?<! \ | " ) " (?! ["' . Patterns::CHAR . '])~Ux' => ':ldq:$1:rdq:',
		// single ''
		'~(?<! \'|\w) \' (?! \ | \' ) ( (?: [^\']++ | \')+ ) (?<! \ |\') \' (?! [\'' . Patterns::CHAR . '])~Ux' => ':lsq:$1:rsq:',
	];

	/** @var array<string, string> */
	private array $localizedReplacements = [];

	/** @var ?list<string> */
	private ?array $compiledQuotes = null;


	public function __construct(
		private Config $config,
	) {
	}


	public function postLine(string $text, bool $preserveSpaces = false): string
	{
		if (!$preserveSpaces) {
			$text = Regexp::replace($text, '~\ {2,}~', ' ');
		}

		return Regexp::replace($text, $this->getReplacements());
	}


	/** @return array<string, string> */
	private function getReplacements(): array
	{
		// compared by value, so both changing the locale and mutating the table invalidate the cache
		$quotes = $this->config->locales[$this->config->locale];
		if ($quotes !== $this->compiledQuotes) {
			$this->compiledQuotes = $quotes;
			$placeholders = array_combine([':ldq:', ':rdq:', ':lsq:', ':rsq:'], $quotes);
			foreach ($this->replacements as $k => $v) {
				$this->localizedReplacements[$k] = strtr($v, $placeholders);
			}
		}

		return $this->localizedReplacements;
	}
}
