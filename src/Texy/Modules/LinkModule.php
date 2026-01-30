<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\HandlerInvocation;
use Texy\Link;
use Texy\Patterns;
use Texy\Regexp;
use function str_contains, str_replace, strlen, strncasecmp, strpos, substr, trim, urlencode;


/**
 * Processes link references and generates link elements.
 */
final class LinkModule extends Texy\Module
{
	/** root of relative links */
	public ?string $root = null;

	/** always use rel="nofollow" for absolute links? */
	public bool $forceNoFollow = false;

	/** @var array<string, Link> link references */
	private array $references = [];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['link/definition'] = true;
	}


	public function beforeParse(string &$text): void
	{
		// [la trine]: http://www.latrine.cz/ text odkazu .(title)[class]{style}
		if (!empty($this->texy->allowed['link/definition'])) {
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
				$this->parseDefinition(...),
			);
		}
	}


	/**
	 * Parses [la trine]: http://www.latrine.cz/
	 * @param  array<?string>  $matches
	 */
	private function parseDefinition(array $matches): string
	{
		/** @var array{string, string, string, ?string, ?string} $matches */
		[, $mRef, $mLink, $mLabel, $mMod] = $matches;
		if ($mMod || $mLabel) {
			trigger_error('Modifiers and label in link definitions are deprecated.', E_USER_DEPRECATED);
		}

		$link = new Link($mLink);
		$this->checkLink($link);
		$link->name = Texy\Helpers::toLower($mRef);
		$this->references[$link->name] = $link;
		return '';
	}


	/**
	 * Adds a user-defined link definition (persists across process() calls).
	 */
	public function addDefinition(string $name, string $url): void
	{
		$link = new Link($url);
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
			}
		}

		if (empty($link)) {
			$link = new Link(trim($dest));
			$this->checkLink($link);
		}

		if (str_contains((string) $link->URL, '%s')) {
			$link->URL = str_replace('%s', urlencode($texy->stringToText($label ?? '')), $link->URL);
		}

		$link->modifier->setProperties($mMod);
		$link->type = $type;
		return $link;
	}


	/**
	 * Generates <a> element from Link.
	 */
	public function solve(
		?HandlerInvocation $invocation,
		Link $link,
		Texy\HtmlElement|string|null $content = null,
	): Texy\HtmlElement|string|null
	{
		if ($link->URL === null) {
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
			$el->attrs['href'] = Texy\Helpers::prependRoot($link->URL, $texy->imageModule->root);
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

		return $el;
	}


	/**
	 * Checks and corrects URL in Link.
	 */
	public function checkLink(Link $link): void
	{
		if ($link->URL === null) {
			return;
		}

		// remove soft hyphens; if not removed by Texy\Texy::process()
		$link->URL = str_replace("\u{AD}", '', $link->URL);

		if (strncasecmp($link->URL, 'www.', 4) === 0) {
			// special supported case
			$link->URL = 'http://' . $link->URL;

		} elseif (Regexp::match($link->URL, '~' . Patterns::EMAIL . '$~A')) {
			// email
			$link->URL = 'mailto:' . $link->URL;

		} elseif (!$this->texy->checkURL($link->URL, Texy\Texy::FILTER_ANCHOR)) {
			$link->URL = null;

		} else {
			$link->URL = str_replace('&amp;', '&', $link->URL); // replace unwanted &amp;
		}
	}
}
