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


/**
 * Links module.
 */
final class LinkModule extends Texy\Module
{
	/** @var string  root of relative links */
	public $root = '';

	/** @var string|null linked image class */
	public $imageClass;

	/** @var bool  always use rel="nofollow" for absolute links? */
	public $forceNoFollow = false;

	/** @var bool  shorten URLs to more readable form? */
	public $shorten = true;

	/** @var array<string, Link> link references */
	private $references = [];

	/** @var array<string, bool> */
	private static $livelock;

	/** @var string */
	private static $EMAIL;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->allowed['link/definition'] = true;
		$texy->addHandler('newReference', [$this, 'solveNewReference']);
		$texy->addHandler('linkReference', [$this, 'solve']);
		$texy->addHandler('linkEmail', [$this, 'solveUrlEmail']);
		$texy->addHandler('linkURL', [$this, 'solveUrlEmail']);
		$texy->addHandler('beforeParse', [$this, 'beforeParse']);

		// [reference]
		$texy->registerLinePattern(
			[$this, 'patternReference'],
			'#(\[[^\[\]\*\n' . Patterns::MARK . ']++\])#U',
			'link/reference'
		);

		// direct url; charaters not allowed in URL <>[\]^`{|}
		$texy->registerLinePattern(
			[$this, 'patternUrlEmail'],
			'#(?<=^|[\s([<:\x17])(?:https?://|www\.|ftp://)[0-9.' . Patterns::CHAR . '-][/\d' . Patterns::CHAR . '+\.~%&?@=_:;\#$!,*()\x{ad}-]{1,1000}[/\d' . Patterns::CHAR . '+~?@=_\#$*]#u',
			'link/url',
			'#(?:https?://|www\.|ftp://)#u'
		);

		// direct email
		self::$EMAIL = '[' . Patterns::CHAR . '][0-9.+_' . Patterns::CHAR . '-]{0,63}@[0-9.+_' . Patterns::CHAR . '\x{ad}-]{1,252}\.[' . Patterns::CHAR . '\x{ad}]{2,19}';
		$texy->registerLinePattern(
			[$this, 'patternUrlEmail'],
			'#(?<=^|[\s([<\x17])' . self::$EMAIL . '#u',
			'link/email',
			'#' . self::$EMAIL . '#u'
		);
	}


	/**
	 * Text pre-processing.
	 */
	public function beforeParse(Texy\Texy $texy, &$text): void
	{
		self::$livelock = [];

		// [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}
		if (!empty($texy->allowed['link/definition'])) {
			$text = Texy\Regexp::replace(
				$text,
				'#^\[([^\[\]\#\?\*\n]{1,100})\]: ++(\S{1,1000})(\ .{1,1000})?' . Patterns::MODIFIER . '?\s*()$#mUu',
				[$this, 'patternReferenceDef']
			);
		}
	}


	/**
	 * Callback for: [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}.
	 * @internal
	 */
	public function patternReferenceDef(array $matches): string
	{
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		// [1] => [ (reference) ]
		// [2] => link
		// [3] => ...
		// [4] => .(title)[class]{style}

		$link = new Link($mLink);
		$link->label = trim($mLabel);
		$link->modifier->setProperties($mMod);
		$this->checkLink($link);
		$this->addReference($mRef, $link);
		return '';
	}


	/**
	 * Callback for: [ref].
	 * @return Texy\HtmlElement|string|null
	 */
	public function patternReference(LineParser $parser, array $matches)
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
				$lineParser = new LineParser($texy, $el);
				$lineParser->parse($link->label);
				$content = $el->toString($texy);
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
	 * @return Texy\HtmlElement|string|null
	 */
	public function patternUrlEmail(LineParser $parser, array $matches, string $name)
	{
		[$mURL] = $matches;
		// [0] => URL

		$link = new Link($mURL);
		$this->checkLink($link);

		return $this->texy->invokeAroundHandlers(
			$name === 'link/email' ? 'linkEmail' : 'linkURL',
			$parser,
			[$link]
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
				$link = new Link($image->linkedURL === null ? $image->URL : $image->linkedURL);
				$link->modifier = $image->modifier;
			}
		}

		if (empty($link)) {
			$link = new Link(trim($dest));
			$this->checkLink($link);
		}

		if (strpos((string) $link->URL, '%s') !== false) {
			$link->URL = str_replace('%s', urlencode($texy->stringToText($label)), $link->URL);
		}
		$link->modifier->setProperties($mMod);
		$link->type = $type;
		return $link;
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HtmlElement|string $content
	 * @return Texy\HtmlElement|string
	 */
	public function solve(HandlerInvocation $invocation = null, Link $link, $content = null)
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
			if ($nofollow || ($this->forceNoFollow && strpos($el->attrs['href'], '//') !== false)) {
				$el->attrs['rel'] = 'nofollow';
			}
		}

		if ($content !== null) {
			$el->add($content);
		}

		$texy->summary['links'][] = $el->attrs['href'];

		return $el;
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|string
	 */
	public function solveUrlEmail(HandlerInvocation $invocation, Link $link)
	{
		$content = $this->textualUrl($link);
		$content = $this->texy->protect($content, Texy\Texy::CONTENT_TEXTUAL);
		return $this->solve(null, $link, $content);
	}


	/**
	 * Finish invocation.
	 */
	public function solveNewReference(HandlerInvocation $invocation, string $name)
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

		} elseif (preg_match('#' . self::$EMAIL . '$#Au', $link->URL)) {
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
		if ($this->texy->obfuscateEmail && preg_match('#^' . self::$EMAIL . '$#u', $link->raw)) { // email
			return str_replace('@', '&#64;<!-- -->', $link->raw);
		}

		if ($this->shorten && preg_match('#^(https?://|ftp://|www\.|/)#i', $link->raw)) {
			$raw = strncasecmp($link->raw, 'www.', 4) === 0 ? 'none://' . $link->raw : $link->raw;

			// parse_url() in PHP damages UTF-8 - use regular expression
			if (!preg_match('~^(?:(?P<scheme>[a-z]+):)?(?://(?P<host>[^/?#]+))?(?P<path>(?:/|^)(?!/)[^?#]*)?(?:\?(?P<query>[^#]*))?(?:#(?P<fragment>.*))?()$~', $raw, $parts)) {
				return $link->raw;
			}

			$res = '';
			if ($parts['scheme'] !== '' && $parts['scheme'] !== 'none') {
				$res .= $parts['scheme'] . '://';
			}

			if ($parts['host'] !== '') {
				$res .= $parts['host'];
			}

			if ($parts['path'] !== '') {
				$res .= (iconv_strlen($parts['path'], 'UTF-8') > 16 ? ("/\u{2026}" . iconv_substr($parts['path'], -12, 12, 'UTF-8')) : $parts['path']);
			}

			if ($parts['query'] !== '') {
				$res .= iconv_strlen($parts['query'], 'UTF-8') > 4 ? "?\u{2026}" : ('?' . $parts['query']);
			} elseif ($parts['fragment'] !== '') {
				$res .= iconv_strlen($parts['fragment'], 'UTF-8') > 4 ? "#\u{2026}" : ('#' . $parts['fragment']);
			}
			return $res;
		}

		return $link->raw;
	}
}
