<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use Texy\Output\Html\Schema;
use function array_flip, is_array, is_string, trim;


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
	public bool|array $allowedClasses = Texy::ALL;

	/** @var bool|array<int, string>  allowed inline CSS styles */
	public bool|array $allowedStyles = Texy::ALL;

	/** @var array<string, int>|bool */
	private bool|array $classes;

	/** @var array<string, int>|bool */
	private bool|array $styles;


	public function __construct(
		private Texy $texy,
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
				if (!$this->texy->urlPolicy->isLinkAllowed($attrs['href'])) {
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
			if (is_string($attrs['src']) && !$this->texy->urlPolicy->isImageAllowed($attrs['src'])) {
				return 'drop'; // XSS protection - drop dangerous URLs entirely
			}
		}

		return true;
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


	/**
	 * Allowed classes and styles as lookup sets (or the ALL/NONE booleans).
	 * @internal
	 * @return array{array<string, int>|bool, array<string, int>|bool}
	 */
	public function getAllowedProps(): array
	{
		$this->classes ??= is_array($this->allowedClasses)
			? array_flip($this->allowedClasses)
			: $this->allowedClasses;
		$this->styles ??= is_array($this->allowedStyles)
			? array_flip($this->allowedStyles)
			: $this->allowedStyles;

		return [$this->classes, $this->styles];
	}


	/** @internal */
	public function resetCache(): void
	{
		unset($this->classes, $this->styles);
	}
}
