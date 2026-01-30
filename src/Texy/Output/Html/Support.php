<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Html;

use Texy\Modifier;
use Texy\Nodes;
use Texy\Texy;


/**
 * Support methods for HTML rendering.
 */
final class Support
{
	public function __construct(
		private Texy $texy,
		private Generator $generator,
	) {
	}


	/**
	 * Analyze paragraph content to determine what types of nodes it contains.
	 * @param  array<Nodes\BlockNode|Nodes\InlineNode>  $content
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasOther: bool}
	 */
	public function analyzeContent(array $content): array
	{
		$hasText = false;
		$hasReplaced = false;
		$hasMarkup = false;
		$hasOther = false;

		foreach ($content as $node) {
			if ($node instanceof Nodes\TextNode) {
				$hasText = $hasText || trim($node->content) !== '';

			} elseif ($node instanceof Nodes\ImageNode) {
				$hasReplaced = true;

			} elseif ($node instanceof Nodes\HtmlCommentNode) {
				$hasMarkup = true;

			} elseif ($node instanceof Nodes\HtmlTagNode) {
				if ($node->closing) {
					continue;
				}
				$inlineType = Element::$inlineElements[strtolower($node->name)] ?? null;
				match ($inlineType) {
					1 => $hasReplaced = true,    // replaced element (img, br, input, ...)
					0 => $hasMarkup = true,      // inline markup (span, a, strong, ...)
					default => $hasMarkup = true,   // block element or unknown
				};

			} elseif ($node instanceof Nodes\LinkNode) {
				$inner = $this->analyzeContent($node->content->children);
				if ($inner['hasText'] || $inner['hasOther']) {
					$hasOther = true;
				} elseif ($inner['hasReplaced']) {
					$hasReplaced = true;
				}

			} else {
				$hasOther = true;
			}
		}

		return compact('hasText', 'hasReplaced', 'hasMarkup', 'hasOther');
	}


	/**
	 * Wrap children in a null element (no tag wrapper).
	 * @param array<Element|string> $children
	 */
	public function wrapChildren(array $children): Element
	{
		$el = new Element(null);
		$el->children = $children;
		return $el;
	}


	/**
	 * Create paragraph for non-text content (images only).
	 * @param array<Element|string> $children
	 */
	public function createNontextParagraph(array $children, ?Modifier $modifier): Element
	{
		$nontextParagraph = $this->generator->nontextParagraph;
		if ($nontextParagraph instanceof Element) {
			$el = clone $nontextParagraph;
			$this->generator->decorateElement($modifier, $el);
			$el->children = $children;
			return $el;
		}
		$el = new Element($nontextParagraph);
		$this->generator->decorateElement($modifier, $el);
		$el->children = $children;
		return $el;
	}


	/**
	 * Validate HTML tag for security.
	 * Returns: true = valid, false = escape as text, 'drop' = remove entirely
	 * @param  array<string, string|bool>  $attrs
	 */
	public function validateHtmlTag(string $tagName, array $attrs, bool $closing): bool|string
	{
		// <a> requires href, name, or id
		if ($tagName === 'a' && !$closing) {
			if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
				return false;
			}
			// Validate href URL scheme (security: filter javascript: etc.)
			if (isset($attrs['href']) && is_string($attrs['href'])) {
				if (!$this->texy->checkURL($attrs['href'], Texy::FILTER_ANCHOR)) {
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
			if (is_string($attrs['src']) && !$this->texy->checkURL($attrs['src'], Texy::FILTER_IMAGE)) {
				return 'drop'; // XSS protection - drop dangerous URLs entirely
			}
		}

		return true;
	}


	/**
	 * Escape tag as text (when validation fails).
	 * Only escapes < and > so quotes remain for typography processing.
	 */
	public function escapeHtmlTag(Nodes\HtmlTagNode $node): string
	{
		// Reconstruct the original tag text
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
		$tag .= '>';

		// Only escape angle brackets, leave quotes for typography
		return str_replace(['<', '>'], ['&lt;', '&gt;'], $tag);
	}
}
