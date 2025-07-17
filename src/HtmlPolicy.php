<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use Texy\Output\Html\Schema;
use function is_array, is_string;


/**
 * Security policy for HTML in the document: which tags with which attributes,
 * classes, IDs and inline styles may appear.
 */
final class HtmlPolicy
{
	/** @var bool|array<string, bool|array<int, string>>  allowed HTML tags and their attributes */
	public bool|array $allowedTags;

	/** @var bool|array<int, string>  allowed classes and IDs */
	public bool|array $allowedClasses = Texy::ALL;

	/** @var bool|array<int, string>  allowed inline CSS styles */
	public bool|array $allowedStyles = Texy::ALL;


	public function __construct(
		public readonly UrlPolicy $urlPolicy,
	) {
		$this->allowedTags = Schema::defaultAllowedTags(); // accept all valid HTML tags and attributes by default
	}


	/**
	 * Is the tag (opening or closing) acceptable? A rejected one is shown as text.
	 * @param  array<string, string|bool|null>  $attrs
	 */
	public function isTagAcceptable(string $tagName, array $attrs, bool $closing): bool
	{
		return $this->isTagAllowed($tagName) && $this->validateTag($tagName, $attrs, $closing);
	}


	/**
	 * Is tag allowed by the allowedTags whitelist?
	 */
	private function isTagAllowed(string $tagName): bool
	{
		if (!$this->allowedTags) { // all tags are disabled
			return false;
		}

		return !is_array($this->allowedTags) || isset($this->allowedTags[$tagName]);
	}


	/**
	 * Does the tag have what it needs, and do its URLs pass the URL policy?
	 * @param  array<string, string|bool|null>  $attrs
	 */
	private function validateTag(string $tagName, array $attrs, bool $closing): bool
	{
		// <a> requires href, name, or id
		if ($tagName === 'a' && !$closing) {
			if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
				return false;
			}
			// Validate href URL scheme (security: filter javascript: etc.)
			if (isset($attrs['href']) && is_string($attrs['href'])) {
				if (!$this->urlPolicy->isLinkAllowed($attrs['href'])) {
					return false;
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
				return false;
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
			if ($allowed === Texy::ALL) {
				return $attrs;
			} elseif (is_array($allowed)) {
				$names = [...$names, ...$allowed];
			}
		}

		return array_intersect_key($attrs, array_flip($names));
	}


	/**
	 * A URL attribute survives only if UrlPolicy accepts it. This holds regardless of
	 * the whitelist - "javascript:" must not get through even on a permissive policy.
	 * @template T
	 * @param  array<string, T>  $attrs
	 * @return array<string, T>
	 */
	public function filterUrls(array $attrs): array
	{
		foreach ($attrs as $name => $value) {
			$kind = Schema::urlAttribute($name);
			if ($kind === null || !is_string($value)) {
				continue;
			}

			$allowed = $kind === Schema::UrlImage
				? $this->urlPolicy->isImageAllowed($value)
				: $this->urlPolicy->isLinkAllowed($value);
			if (!$allowed) {
				unset($attrs[$name]);
			}
		}

		return $attrs;
	}


	/**
	 * Is the class allowed by the allowedClasses whitelist?
	 */
	public function isClassAllowed(string $class): bool
	{
		return is_array($this->allowedClasses)
			? in_array($class, $this->allowedClasses, true)
			: $this->allowedClasses;
	}


	/**
	 * Is the ID allowed by the allowedClasses whitelist?
	 */
	public function isIdAllowed(string $id): bool
	{
		return $this->isClassAllowed('#' . $id);
	}


	/**
	 * Is the CSS property allowed by the allowedStyles whitelist?
	 */
	public function isStyleAllowed(string $property): bool
	{
		return is_array($this->allowedStyles)
			? in_array($property, $this->allowedStyles, true)
			: $this->allowedStyles;
	}
}
