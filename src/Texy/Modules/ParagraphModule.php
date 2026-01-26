<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes;
use Texy\Nodes\ParagraphNode;
use Texy\Output\Html\Generator;
use function is_array, strtolower, trim;


/**
 * Processes paragraphs and handles line breaks.
 */
final class ParagraphModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solve(...));
		$texy->htmlGenerator->registerHandler(
			fn(Nodes\LineBreakNode $node) => $this->texy->protect('<br>', $this->texy::CONTENT_REPLACED),
		);
	}


	public function solve(ParagraphNode $node, Generator $generator): string
	{
		$content = $generator->generateInlineContent($node->content);
		$attrs = $generator->generateModifierAttrs($node->modifier);

		// Block HTML content - skip <p> wrapper entirely
		if ($node->blockHtml) {
			return trim($content);
		}

		// Check if paragraph contains only markup (HTML tags/comments) without text
		// In that case, skip the <p> wrapper
		if ($node->modifier === null && $this->isOnlyMarkupContent($node->content)) {
			return trim($content);
		}

		// Check if paragraph contains only replaced elements (images)
		// In that case, use nontextParagraph tag (default: div) instead of <p>
		$tag = 'p';
		if ($this->isOnlyReplacedContent($node->content)) {
			$nontextParagraph = $this->texy->nontextParagraph;
			if ($nontextParagraph instanceof Texy\HtmlElement) {
				$el = clone $nontextParagraph;
				// Merge paragraph modifier classes into the element
				if ($node->modifier !== null) {
					// Ensure class is array before appending
					if (!is_array($el->attrs['class'] ?? null)) {
						$existing = $el->attrs['class'] ?? null;
						$el->attrs['class'] = $existing !== null ? [$existing] : [];
					}
					foreach ($node->modifier->classes as $class => $_) {
						$el->attrs['class'][] = $class;
					}
					if ($node->modifier->id !== null) {
						$el->attrs['id'] = $node->modifier->id;
					}
				}
				return $this->texy->protect($el->startTag(), $this->texy::CONTENT_BLOCK)
					. $content
					. $this->texy->protect($el->endTag(), $this->texy::CONTENT_BLOCK);
			}
			$tag = $nontextParagraph;
		}

		$open = $this->texy->protect("<{$tag}{$attrs}>", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("</{$tag}>", $this->texy::CONTENT_BLOCK);
		return $open . $content . $close;
	}


	/**
	 * Check if content contains only replaced elements (images) and no text.
	 * @param array<Nodes\InlineNode> $content
	 */
	private function isOnlyReplacedContent(array $content): bool
	{
		$hasReplaced = false;
		foreach ($content as $node) {
			if ($node instanceof Nodes\ImageNode) {
				$hasReplaced = true;
			} elseif ($node instanceof Nodes\LinkNode) {
				// Link containing only image is considered replaced
				if ($this->isOnlyReplacedContent($node->content)) {
					$hasReplaced = true;
				} else {
					return false;
				}
			} elseif ($node instanceof Nodes\HtmlTagNode) {
				// Check if this is a replaced HTML element (img, etc.)
				$tagName = strtolower($node->name);
				$inlineType = Texy\HtmlElement::$inlineElements[$tagName] ?? null;
				if ($inlineType === 1) {
					// Inline replaced element (img, br, etc.)
					$hasReplaced = true;
				} elseif ($inlineType === 0) {
					// Inline markup (a, span, etc.) - continue checking
					// but don't mark as having replaced content
				} elseif ($node->closing) {
					// Closing tags don't affect the check
				} else {
					// Block element - not purely replaced content
					return false;
				}
			} elseif ($node instanceof Nodes\TextNode) {
				if (trim($node->content) !== '') {
					return false;
				}
			} else {
				return false;
			}
		}
		return $hasReplaced;
	}


	/**
	 * Check if content contains only markup/block elements without actual text.
	 * @param array<Nodes\InlineNode> $content
	 */
	private function isOnlyMarkupContent(array $content): bool
	{
		$hasMarkup = false;
		foreach ($content as $node) {
			if ($node instanceof Nodes\HtmlCommentNode) {
				$hasMarkup = true;
			} elseif ($node instanceof Nodes\HtmlTagNode) {
				// Check tag type: inline markup (0), inline replaced (1), or block (not in list)
				$tagName = strtolower($node->name);
				$inlineType = Texy\HtmlElement::$inlineElements[$tagName] ?? null;
				if ($inlineType === null) {
					// Block element - skip <p> wrapper
					$hasMarkup = true;
				} elseif ($inlineType === 0) {
					// Inline markup - skip <p> wrapper
					$hasMarkup = true;
				} else {
					// Inline replaced (1) - not markup, needs wrapper
					return false;
				}
			} elseif ($node instanceof Nodes\TextNode) {
				if (trim($node->content) !== '') {
					return false; // Has actual text content
				}
			} else {
				return false; // Other content types (images, links, etc.)
			}
		}
		return $hasMarkup;
	}
}
