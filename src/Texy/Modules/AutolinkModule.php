<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HandlerInvocation;
use Texy\InlineParser;
use Texy\Link;
use Texy\Patterns;
use Texy\Regexp;
use function iconv_strlen, iconv_substr, str_replace, strncasecmp;


/**
 * Autodetects URLs and email addresses in text.
 */
final class AutolinkModule extends Texy\Module
{
	/** shorten URLs to more readable form? */
	public bool $shorten = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler('linkEmail', $this->solveUrlEmail(...));
		$texy->addHandler('linkURL', $this->solveUrlEmail(...));
	}


	public function beforeParse(string &$text): void
	{
		// direct url; characters not allowed in URL <>[\]^`{|}
		$this->texy->registerLinePattern(
			$this->parseUrlEmail(...),
			'~
				(?<= ^ | [\s([<:\x17] )            # must be preceded by these chars
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~',
			'link/url',
			'~(?: https?:// | www\. | ftp://)~',
		);

		// direct email
		$this->texy->registerLinePattern(
			$this->parseUrlEmail(...),
			'~
				(?<= ^ | [\s([<\x17] )             # must be preceded by these chars
				' . Patterns::EMAIL . '
			~',
			'link/email',
			'~' . Patterns::EMAIL . '~',
		);
	}


	/**
	 * Parses http://davidgrudl.com david@grudl.com
	 * @param  array<?string>  $matches
	 */
	public function parseUrlEmail(InlineParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
	{
		[$mURL] = $matches;
		// [0] => URL

		$link = new Link($mURL);
		$this->texy->linkModule->checkLink($link);

		return $this->texy->invokeAroundHandlers(
			$name === 'link/email' ? 'linkEmail' : 'linkURL',
			$parser,
			[$link],
		);
	}


	/**
	 * Handler for URL/email - creates textual content and link element.
	 */
	private function solveUrlEmail(HandlerInvocation $invocation, Link $link): Texy\HtmlElement|string|null
	{
		$content = $this->textualUrl($link);
		$content = $this->texy->protect($content, Texy\Texy::CONTENT_TEXTUAL);
		return $this->texy->linkModule->solve(null, $link, $content);
	}


	/**
	 * Returns textual representation of URL.
	 */
	public function textualUrl(Link $link): string
	{
		if ($this->texy->obfuscateEmail && Regexp::match($link->raw, '~^' . Patterns::EMAIL . '$~')) { // email
			return str_replace('@', '&#64;<!-- -->', $link->raw);
		}

		if ($this->shorten && Regexp::match($link->raw, '~^(https?://|ftp://|www\.|/)~i')) {
			$raw = strncasecmp($link->raw, 'www.', 4) === 0
				? 'none://' . $link->raw
				: $link->raw;

			// parse_url() in PHP damages UTF-8 - use regular expression
			if (!($parts = Regexp::match($raw, '~^
				(?: (?P<scheme> [a-z]+ ) : )?
				(?: // (?P<host> [^/?#]+ ) )?
				(?P<path> (?: / | ^ ) (?! / ) [^?#]* )?
				(?: \? (?P<query> [^#]* ) )?
				(?: \# (?P<fragment> .* ) )?
				$
			~'))) {
				return $link->raw;
			}

			$res = '';
			if ($parts['scheme'] !== null && $parts['scheme'] !== 'none') {
				$res .= $parts['scheme'] . '://';
			}

			if ($parts['host'] !== null) {
				$res .= $parts['host'];
			}

			if ($parts['path'] !== null) {
				$res .= (iconv_strlen($parts['path'], 'UTF-8') > 16 ? ("/\u{2026}" . iconv_substr($parts['path'], -12, 12, 'UTF-8')) : $parts['path']);
			}

			if ($parts['query'] > '') {
				$res .= iconv_strlen($parts['query'], 'UTF-8') > 4
					? "?\u{2026}"
					: ('?' . $parts['query']);
			} elseif ($parts['fragment'] > '') {
				$res .= iconv_strlen($parts['fragment'], 'UTF-8') > 4
					? "#\u{2026}"
					: ('#' . $parts['fragment']);
			}

			return $res;
		}

		return $link->raw;
	}
}
