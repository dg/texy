<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use Texy\Output\Html\Schema;
use function is_array, is_string;


/**
 * Security policy for HTML in the document: which tags, classes and inline
 * styles may appear, plus validation and reconstruction of passthrough
 * HTML tags.
 */
final class HtmlPolicy
{
	/** @var bool|array<string, bool|array<int, string>>  allowed HTML tags and their attributes */
	public bool|array $allowedTags;

	/** @var bool|array<int, string>  allowed classes and IDs */
	public bool|array $allowedClasses = Texy::All;

	/** @var bool|array<int, string>  allowed inline CSS styles */
	public bool|array $allowedStyles = Texy::All;


	public function __construct(
		private UrlPolicy $urlPolicy,
	) {
		$this->allowedTags = Schema::defaultAllowedTags(); // accept all valid HTML tags and attributes by default
	}


	/**
	 * Is tag allowed by the allowedTags whitelist?
	 */
	public function isTagAllowed(string $tagName): bool
	{
		if (!$this->allowedTags) { // all tags are disabled
			return false;
		}

		return !is_array($this->allowedTags) || isset($this->allowedTags[$tagName]);
	}


	/**
	 * Validate HTML tag for security.
	 * Returns: true = valid, false = escape as text, 'drop' = remove entirely
	 * @param  array<string, string|bool|null>  $attrs
	 */
	public function validateTag(string $tagName, array $attrs, bool $closing): bool|string
	{
		// <a> requires href, name, or id
		if ($tagName === 'a' && !$closing) {
			if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
				return false;
			}
			// Validate href URL scheme (security: filter javascript: etc.)
			if (isset($attrs['href']) && is_string($attrs['href'])) {
				if (!$this->urlPolicy->isLinkAllowed($attrs['href'])) {
					return 'drop'; // XSS protection - drop dangerous URLs entirely
				}
			}
		}

		// <img> requires src
		if ($tagName === 'img') {
			if (!isset($attrs['src']) || (is_string($attrs['src']) && trim($attrs['src']) === '')) {
				return false;
			}
			// Validate src URL scheme
			if (is_string($attrs['src']) && !$this->urlPolicy->isImageAllowed($attrs['src'])) {
				return 'drop'; // XSS protection - drop dangerous URLs entirely
			}
		}

		return true;
	}


	/**
	 * Drops attributes the whitelist does not allow for the tag. An accepted tag
	 * must not smuggle in whatever attribute it likes - allowedTags names both the
	 * tags and their attributes, and event handlers like onclick get in this way.
	 *
	 * The '*' key lists attributes allowed on any element; it is added to whatever
	 * the element itself allows.
	 * @template T
	 * @param  array<string, T>  $attrs  values are untouched, only keys are filtered
	 * @return array<string, T>
	 */
	public function filterAttrs(string $tagName, array $attrs): array
	{
		if (!is_array($this->allowedTags)) {
			return $this->allowedTags ? $attrs : [];
		}

		$names = [];
		foreach ([$this->allowedTags[$tagName] ?? [], $this->allowedTags['*'] ?? []] as $allowed) {
			if ($allowed === Texy::All) {
				return $attrs;
			} elseif (is_array($allowed)) {
				$names = array_merge($names, $allowed);
			}
		}

		return array_intersect_key($attrs, array_flip($names));
	}


	/**
	 * Is the tag (opening or closing) acceptable? Mirrors the render-time
	 * decision so it can also be evaluated in the transform phase.
	 * @param  array<string, string|bool|null>  $attrs
	 */
	public function isTagAcceptable(string $tagName, array $attrs, bool $closing): bool
	{
		if (!$this->isTagAllowed($tagName)) {
			return false;
		}

		$validation = $this->validateTag($tagName, $attrs, $closing);
		return $validation !== false && $validation !== 'drop';
	}


	/**
	 * Reconstructs the original tag source text.
	 */
	public function reconstructTag(Nodes\HtmlTagNode $node): string
	{
		$tag = '<';
		if ($node->closing) {
			$tag .= '/';
		}
		$tag .= $node->name;
		foreach ($node->attributes as $name => $value) {
			$tag .= ' ' . $name;
			if ($value !== true) {
				$tag .= '="' . $value . '"';
			}
		}
		if ($node->selfClosing) {
			$tag .= ' /';
		}

		return $tag . '>';
	}
}
