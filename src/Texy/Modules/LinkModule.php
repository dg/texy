<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HandlerInvocation;
use Texy\LineParser;
use Texy\Link;
use Texy\Patterns;
use Texy\Regexp;


/**
 * Links module.
 */
final class LinkModule extends Texy\Module
{
	/** root of relative links */
	public ?string $root = null;

	/** linked image class */
	public ?string $imageClass = null;

	/** always use rel="nofollow" for absolute links? */
	public bool $forceNoFollow = false;

	/** shorten URLs to more readable form? */
	public bool $shorten = true;

	/** @var array<string, Link> link references */
	private array $references = [];

	/** @var array<string, bool> */
	private static array $livelock;

	private static string $EMAIL;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->allowed['link/definition'] = true;
		$texy->addHandler('newReference', $this->newReferenceToElement(...));
		$texy->addHandler('linkReference', $this->linkToElement(...));
		$texy->addHandler('linkEmail', $this->urlEmailToElement(...));
		$texy->addHandler('linkURL', $this->urlEmailToElement(...));
		$texy->addHandler('beforeParse', $this->beforeParse(...));

		// [reference]
		$texy->registerLinePattern(
			$this->parseReference(...),
			'~(
				\[
				[^\[\]*\n' . Patterns::MARK . ']++  # reference
				]
			)~U',
			'link/reference',
		);

		// direct url; characters not allowed in URL <>[\]^`{|}
		$texy->registerLinePattern(
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
		self::$EMAIL = '
			[' . Patterns::CHAR . ']                 # first char
			[0-9.+_' . Patterns::CHAR . '-]{0,63}    # local part
			@
			[0-9.+_' . Patterns::CHAR . '\x{ad}-]{1,252} # domain
			\.
			[' . Patterns::CHAR . '\x{ad}]{2,19}     # TLD
		';
		$texy->registerLinePattern(
			$this->parseUrlEmail(...),
			'~
				(?<= ^ | [\s([<\x17] )             # must be preceded by these chars
				' . self::$EMAIL . '
			~',
			'link/email',
			'~' . self::$EMAIL . '~',
		);
	}


	/**
	 * Text pre-processing.
	 */
	private function beforeParse(Texy\Texy $texy, &$text): void
	{
		self::$livelock = [];

		// [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}
		if (!empty($texy->allowed['link/definition'])) {
			$text = Texy\Regexp::replace(
				$text,
				'~^
					\[
					( [^\[\]#?*\n]{1,100} )           # reference (1)
					] : \ ++
					( \S{1,1000} )                    # URL (2)
					( [ \t] .{1,1000} )?              # optional description (3)
					' . Patterns::MODIFIER . '?       # modifier (4)
					\s*
				$~mU',
				$this->parseReferenceDef(...),
			);
		}
	}


	/**
	 * Callback for: [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}.
	 */
	private function parseReferenceDef(array $matches): string
	{
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		// [1] => [ (reference) ]
		// [2] => link
		// [3] => ...
		// [4] => .(title)[class]{style}

		$link = new Link($mLink);
		$link->label = trim($mLabel ?? '');
		$link->modifier->setProperties($mMod);
		$this->checkLink($link);
		$this->addReference($mRef, $link);
		return '';
	}


	/**
	 * Callback for: [ref].
	 */
	public function parseReference(LineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		[, $mRef] = $matches;
		// [1] => [ref]

		$texy = $this->texy;
		$name = substr($mRef, 1, -1);
		$link = $this->getReference($name);

		if (!$link) {
			return $texy->invokeAroundHandlers('newReference', $parser, [$name]);
		}

		$link->type = $link::BRACKET;

		if ($link->label != '') { // null or ''
			// prevent circular references
			if (isset(self::$livelock[$link->name])) {
				$content = $link->label;
			} else {
				self::$livelock[$link->name] = true;
				$el = new Texy\HtmlElement;
				$lineParser = new LineParser($texy);
				$el->inject($lineParser->parse($link->label));
				$content = $texy->elemToMaskedString($el);
				unset(self::$livelock[$link->name]);
			}
		} else {
			$content = $this->textualUrl($link);
			$content = $this->texy->protect($content, $texy::CONTENT_TEXTUAL);
		}

		return $texy->invokeAroundHandlers('linkReference', $parser, [$link, $content]);
	}


	/**
	 * Callback for: http://davidgrudl.com david@grudl.com.
	 */
	public function parseUrlEmail(LineParser $parser, array $matches, string $name): Texy\HtmlElement|string|null
	{
		[$mURL] = $matches;
		// [0] => URL

		$link = new Link($mURL);
		$this->checkLink($link);

		return $this->texy->invokeAroundHandlers(
			$name === 'link/email' ? 'linkEmail' : 'linkURL',
			$parser,
			[$link],
		);
	}


	/**
	 * Adds new named reference.
	 */
	public function addReference(string $name, Link $link): void
	{
		$link->name = Texy\Helpers::toLower($name);
		$this->references[$link->name] = $link;
	}


	/**
	 * Returns named reference.
	 */
	public function getReference(string $name): ?Link
	{
		$name = Texy\Helpers::toLower($name);
		if (isset($this->references[$name])) {
			return clone $this->references[$name];

		} else {
			$pos = strpos($name, '?');
			if ($pos === false) {
				$pos = strpos($name, '#');
			}

			if ($pos !== false) { // try to extract ?... #... part
				$name2 = substr($name, 0, $pos);
				if (isset($this->references[$name2])) {
					$link = clone $this->references[$name2];
					$link->URL .= substr($name, $pos);
					return $link;
				}
			}
		}

		return null;
	}


	public function factoryLink(string $dest, ?string $mMod, ?string $label): Link
	{
		$texy = $this->texy;
		$type = Link::COMMON;

		// [ref]
		if (strlen($dest) > 1 && $dest[0] === '[' && $dest[1] !== '*') {
			$type = Link::BRACKET;
			$dest = substr($dest, 1, -1);
			$link = $this->getReference($dest);

		// [* image *]
		} elseif (strlen($dest) > 1 && $dest[0] === '[' && $dest[1] === '*') {
			$type = Link::IMAGE;
			$dest = trim(substr($dest, 2, -2));
			$image = $texy->imageModule->getReference($dest);
			if ($image) {
				$link = new Link($image->linkedURL ?? $image->URL);
				$link->modifier = $image->modifier;
			}
		}

		if (empty($link)) {
			$link = new Link(trim($dest));
			$this->checkLink($link);
		}

		if (str_contains((string) $link->URL, '%s')) {
			$link->URL = str_replace('%s', urlencode($texy->maskedStringToText($label)), $link->URL);
		}

		$link->modifier->setProperties($mMod);
		$link->type = $type;
		return $link;
	}


	public function linkToElement(
		?HandlerInvocation $invocation,
		Link $link,
		Texy\HtmlElement|string|null $content = null,
	): Texy\HtmlElement|string
	{
		if ($link->URL == null) {
			return $content;
		}

		$texy = $this->texy;

		$el = new Texy\HtmlElement('a');

		if (empty($link->modifier)) {
			$nofollow = false;
		} else {
			$nofollow = isset($link->modifier->classes['nofollow']);
			unset($link->modifier->classes['nofollow']);
			$el->attrs['href'] = null; // trick - move to front
			$link->modifier->decorate($texy, $el);
		}

		if ($link->type === Link::IMAGE) {
			// image
			$el->attrs['href'] = Texy\Helpers::prependRoot($link->URL, $texy->imageModule->linkedRoot);
			if ($this->imageClass) {
				$el->attrs['class'][] = $this->imageClass;
			}
		} else {
			$el->attrs['href'] = Texy\Helpers::prependRoot($link->URL, $this->root);

			// rel="nofollow"
			if ($nofollow || ($this->forceNoFollow && str_contains($el->attrs['href'], '//'))) {
				$el->attrs['rel'] = 'nofollow';
			}
		}

		if ($content !== null) {
			$el->add($content);
		}

		$texy->summary['links'][] = $el->attrs['href'];

		return $el;
	}


	public function urlEmailToElement(HandlerInvocation $invocation, Link $link): Texy\HtmlElement|string
	{
		$content = $this->textualUrl($link);
		$content = $this->texy->protect($content, Texy\Texy::CONTENT_TEXTUAL);
		return $this->linkToElement(null, $link, $content);
	}


	public function newReferenceToElement(HandlerInvocation $invocation, string $name)
	{
		// no change
	}


	/**
	 * Checks and corrects $URL.
	 */
	private function checkLink(Link $link): void
	{
		// remove soft hyphens; if not removed by Texy\Texy::process()
		$link->URL = str_replace("\u{AD}", '', $link->URL);

		if (strncasecmp($link->URL, 'www.', 4) === 0) {
			// special supported case
			$link->URL = 'http://' . $link->URL;

		} elseif (Regexp::match($link->URL, '~' . self::$EMAIL . '$~A')) {
			// email
			$link->URL = 'mailto:' . $link->URL;

		} elseif (!$this->texy->checkURL($link->URL, Texy\Texy::FILTER_ANCHOR)) {
			$link->URL = null;

		} else {
			$link->URL = str_replace('&amp;', '&', $link->URL); // replace unwanted &amp;
		}
	}


	/**
	 * Returns textual representation of URL.
	 */
	private function textualUrl(Link $link): string
	{
		if ($this->texy->obfuscateEmail && Regexp::match($link->raw, '~^' . self::$EMAIL . '$~')) { // email
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
