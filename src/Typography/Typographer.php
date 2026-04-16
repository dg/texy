<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Typography;

use Texy\{Config, Patterns, Regexp};


/**
 * Typographic corrections (quotes, dashes, ellipsis, non-breaking spaces). Its patterns are
 * written over the image of Passes\TextRunPass.
 * @internal
 */
final class Typographer
{
	/**
	 * search => replacement; :ldq:/:rdq:/:lsq:/:rsq: are placeholders for locale quotes,
	 * :words: for the one-letter words of the locale, a rule without them is left out
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

		// TextRunPass image alphabet: \x17 markup boundary (transparent), \x16 replaced content
		'~(?<= [^\s\x17] ) \s++ (\x17++)(?= \s)~x'  => '$1',                      // remove intermarkup space phase 1
		'~(?<= \s) (\x17++) \s++~x' /* .       */ => '$1',                      // remove intermarkup space phase 2

		'~(?<= .{50}) \s++ (?= \x17* \S{1,6} \x17* $)~sx' => "\u{A0}",      // space before last short word

		// nbsp space between number (optionally followed by dot) and word, symbol, punctation, currency symbol
		'~(?<= ^|\ |\.|,|-|\+|\x16|\(|\d\x{A0}) (\x17* \d++ \.? \x17*) \s++ (?= \x17* [%'
		. Patterns::Letter
		. '\x{b0}-\x{be}\x{2020}-\x{214f}])~mx' => "\$1\u{A0}",
		// space after a one-letter word that may not end a line
		'~(?<= ^|[^0-9'
		. Patterns::Letter
		. ']) (\x17* [:words:] \x17*) \s++ (?= \x17* [0-9'
		. Patterns::Letter
		. '])~msx' => "\$1\u{A0}",

		// double ""
		'~(?<! "|\w) " (?! \ | " ) ( (?: [^"]++ | ")+ ) (?<! \ | " ) " (?! ["' . Patterns::Letter . '])~Ux' => ':ldq:$1:rdq:',
		// single ''
		'~(?<! \'|\w) \' (?! \ | \' ) ( (?: [^\']++ | \')+ ) (?<! \ |\') \' (?! [\'' . Patterns::Letter . '])~Ux' => ':lsq:$1:rsq:',
	];

	/** @var array<string, string> */
	private array $localizedReplacements = [];

	/** @var ?array{string, list<string>} */
	private ?array $compiledFor = null;


	public function __construct(
		private Config $config,
	) {
	}


	public function correct(string $text, bool $preserveSpaces = false): string
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
		$words = $this->config->oneLetterWords[$this->config->locale] ?? '';
		$quotes = $this->config->locales[$this->config->locale];
		if ($this->compiledFor !== [$words, $quotes]) {
			$this->compiledFor = [$words, $quotes];
			$placeholders = array_combine([':ldq:', ':rdq:', ':lsq:', ':rsq:'], $quotes);
			$this->localizedReplacements = [];
			foreach ($this->replacements as $k => $v) {
				if (str_contains($k, ':words:')) {
					if ($words === '') {
						continue;
					}
					$k = str_replace(':words:', preg_quote($words, '~'), $k);
				}
				$this->localizedReplacements[$k] = strtr($v, $placeholders);
			}
		}

		return $this->localizedReplacements;
	}
}
