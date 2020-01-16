<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Patterns;


/**
 * Typography replacements module.
 */
final class TypographyModule extends Texy\Module
{
	// @see http://www.unicode.org/cldr/data/charts/by_type/misc.delimiters.html
	public static $locales = [
		'cs' => [
			'singleQuotes' => ["\u{201A}", "\u{2018}"],
			'doubleQuotes' => ["\u{201E}", "\u{201C}"],
		],

		'en' => [
			'singleQuotes' => ["\u{2018}", "\u{2019}"],
			'doubleQuotes' => ["\u{201C}", "\u{201D}"],
		],

		'fr' => [
			'singleQuotes' => ["\u{2039}", "\u{203A}"],
			'doubleQuotes' => ["\u{00AB}", "\u{00BB}"],
		],

		'de' => [
			'singleQuotes' => ["\u{201A}", "\u{2018}"],
			'doubleQuotes' => ["\u{201E}", "\u{201C}"],
		],

		'pl' => [
			'singleQuotes' => ["\u{201A}", "\u{2019}"],
			'doubleQuotes' => ["\u{201E}", "\u{201D}"],
		],
	];

	/** @var string */
	public $locale = 'cs';

	private static $patterns = [
		'#(?<![.\x{2026}])\.{3,4}(?![.\x{2026}])#mu' => "\u{2026}",                // ellipsis  ...
		'#(?<=[\d ]|^)-(?=[\d ]|$)#' /*.          */ => "\u{2013}",                // en dash 123-123
		'#(?<=[^!*+,/:;<=>@\\\\_|-])--(?=[^!*+,/:;<=>@\\\\_|-])#' => "\u{2013}",   // en dash alphanum--alphanum
		'#,-#' /*.                                */ => ",\u{2013}",               // en dash ,-
		'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#' => "\$1\u{A0}\$2\u{A0}\$3",      // date 23. 1. 1978
		'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#' /*.    */ => "\$1\u{A0}\$2",            // date 23. 1.
		'# --- #' /*.                             */ => "\u{A0}\u{2014} ",         // em dash ---
		'# ([\x{2013}\x{2014}])#u' /*.            */ => "\u{A0}\$1",               // &nbsp; behind dash (dash stays at line end)
		'# <-{1,2}> #' /*.                        */ => " \u{2194} ",              // left right arrow <-->
		'#-{1,}> #' /*.                           */ => "\u{2192} ",               // right arrow -->
		'# <-{1,}#' /*.                           */ => " \u{2190} ",              // left arrow <--
		'#={1,}> #' /*.                           */ => "\u{21D2} ",              // right arrow ==>
		'#\+-#' /*.                               */ => "\u{B1}",                  // +-
		'#(\d++) x (?=\d)#' /*.                   */ => "\$1\u{A0}\u{D7}\u{A0}",   // dimension sign 123 x 123...
		'#(\d++)x(?=\d)#' /*.                     */ => "\$1\u{D7}",               // dimension sign 123x123...
		'#(?<=\d)x(?= |,|.|$)#m' /*.              */ => "\u{D7}",                  // dimension sign 123x
		'#(\S ?)\(TM\)#i' /*.                     */ => "\$1\u{2122}",             // trademark (TM)
		'#(\S ?)\(R\)#i' /*.                      */ => "\$1\u{AE}",               // registered (R)
		'#\(C\)( ?\S)#i' /*.                      */ => "\u{A9}\$1",               // copyright (C)
		'#\(EUR\)#' /*.                           */ => "\u{20AC}",                // Euro (EUR)
		'#(\d) (?=\d{3})#' /*.                    */ => "\$1\u{A0}",               // (phone) number 1 123 123 123...

		// CONTENT_MARKUP mark: \x17-\x1F, CONTENT_REPLACED mark: \x16, CONTENT_TEXTUAL mark: \x17
		'#(?<=[^\s\x17])\s++([\x17-\x1F]++)(?=\s)#u' => '$1',                      // remove intermarkup space phase 1
		'#(?<=\s)([\x17-\x1F]++)\s++#u' /*.       */ => '$1',                      // remove intermarkup space phase 2

		'#(?<=.{50})\s++(?=[\x17-\x1F]*\S{1,6}[\x17-\x1F]*$)#us' => "\u{A0}",      // space before last short word

		// nbsp space between number (optionally followed by dot) and word, symbol, punctation, currency symbol
		'#(?<=^| |\.|,|-|\+|\x16|\(|\d\x{A0})([\x17-\x1F]*\d++\.?[\x17-\x1F]*)\s++(?=[\x17-\x1F]*[%' . Patterns::CHAR . '\x{b0}-\x{be}\x{2020}-\x{214f}])#mu' => "\$1\u{A0}",
		// space between preposition and word
		'#(?<=^|[^0-9' . Patterns::CHAR . '])([\x17-\x1F]*[ksvzouiKSVZOUIA][\x17-\x1F]*)\s++(?=[\x17-\x1F]*[0-9' . Patterns::CHAR . '])#mus' => "\$1\u{A0}",

		// double ""
		'#(?<!"|\w)"(?!\ |")((?:[^"]++|")+)(?<!\ |")"(?!["' . Patterns::CHAR . '])()#Uu' => ':ldq:$1:rdq:',
		// single ''
		'#(?<!\'|\w)\'(?!\ |\')((?:[^\']++|\')+)(?<!\ |\')\'(?![\'' . Patterns::CHAR . '])()#Uu' => ':lsq:$1:rsq:',
	];

	/** @var array */
	private $pattern = [];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;
		$texy->registerPostLine([$this, 'postLine'], 'typography');
		$texy->addHandler('beforeParse', [$this, 'beforeParse']);
	}


	/**
	 * Text pre-processing.
	 */
	public function beforeParse(Texy\Texy $texy, &$text): void
	{
		$locale = self::$locales[$this->locale] ?: self::$locales['en'];
		$dq = $locale['doubleQuotes'];
		$sq = $locale['singleQuotes'];
		foreach (self::$patterns as $k => $v) {
			$this->pattern[$k] = strtr($v, [':ldq:' => $dq[0], ':rdq:' => $dq[1], ':lsq:' => $sq[0], ':rsq:' => $sq[1]]);
		}
	}


	public function postLine(string $text, bool $preserveSpaces = false): string
	{
		if (!$preserveSpaces) {
			$text = Texy\Regexp::replace($text, '# {2,}#', ' ');
		}
		return Texy\Regexp::replace($text, $this->pattern);
	}
}
