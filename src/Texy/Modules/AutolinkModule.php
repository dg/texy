<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\HandlerInvocation;
use Texy\Helpers;
use Texy\InlineParser;
use Texy\Link;
use Texy\Patterns;
use Texy\Regexp;
use function str_replace;


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
		/** @var array{string} $matches */
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
		} elseif ($this->shorten && Regexp::match($link->raw, '~^(https?://|ftp://|www\.|/)~i')) {
			return Helpers::shortenUrl($link->raw);
		}

		return $link->raw;
	}
}
