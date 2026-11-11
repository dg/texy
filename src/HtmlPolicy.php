<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use Texy\Output\Html\Schema;
use function array_key_exists, is_array, is_int, is_string;


/**
 * Security policy for HTML in the document: which tags, classes and inline
 * styles may appear, plus validation and reconstruction of passthrough
 * HTML tags.
 */
final class HtmlPolicy
{
	/** @var bool|array<array-key, bool|array<int, string>|string>  allowed tags and their attributes, a listed tag has none; true allows any tag, false none */
	public bool|array $allowedTags;

	/** @var bool|array<int, string>  allowed classes and IDs; true allows any, false none */
	public bool|array $allowedClasses = true;

	/** @var bool|array<int, string>  allowed inline CSS styles; true allows any, false none */
	public bool|array $allowedStyles = true;


	public function __construct(
		public readonly UrlPolicy $urlPolicy,
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

		return !is_array($this->allowedTags) || $this->getAllowedAttrs($tagName) !== null;
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
		foreach ([$this->getAllowedAttrs($tagName), $this->getAllowedAttrs('*')] as $allowed) {
			if ($allowed === true) {
				return $attrs;
			}
			$names = [...$names, ...$allowed ?? []];
		}

		$names = array_flip($names);
		return array_filter($attrs, fn($name) => isset($names[strtolower((string) $name)]), ARRAY_FILTER_USE_KEY);
	}


	/**
	 * Attributes the whitelist gives the tag: true for any, a list of lowercase names, or null when the
	 * tag is not in it. Names of tags and attributes are compared regardless of case, as in HTML.
	 * @return true|list<string>|null
	 */
	private function getAllowedAttrs(string $tagName): true|array|null
	{
		assert(is_array($this->allowedTags));
		$tagName = strtolower($tagName);
		$entries = array_key_exists($tagName, $this->allowedTags)
			? [$tagName => $this->allowedTags[$tagName]]
			: $this->allowedTags; // a key in another case, or a tag listed without attributes
		foreach ($entries as $key => $value) {
			if (is_int($key)) {
				if (is_string($value) && strtolower($value) === $tagName) {
					return [];
				}
			} elseif (strtolower($key) === $tagName) {
				return match (true) {
					$value === true => true,
					is_array($value) => array_values(array_map(strtolower(...), $value)),
					default => [],
				};
			}
		}

		return null;
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
	 * Returns the tag as written; a tag the parser did not make is rebuilt from its attributes.
	 */
	public function reconstructTag(Nodes\HtmlTagNode $node): string
	{
		if ($node->source !== null) {
			return strtr($node->source, "\r", "\n"); // \r is the hard break mark of a paragraph
		}

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


	/**
	 * Is the ID allowed by the allowedClasses whitelist?
	 */
	public function isIdAllowed(string $id): bool
	{
		return is_array($this->allowedClasses)
			? in_array('#' . $id, $this->allowedClasses, true)
			: $this->allowedClasses;
	}
}
