<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Nodes\ContentNode;
use Texy\Nodes\LineBreakNode;
use Texy\Nodes\ParagraphNode;
use Texy\Nodes\TextNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use function str_contains;


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


	/**
	 * Parse text into paragraphs (split by blank lines).
	 * @return array<ParagraphNode>
	 */
	public function parseText(ParseContext $context, string $text): array
	{
		$parts = Regexp::split($text, '~(\n{2,})~', skipEmpty: true);
		$res = [];
		foreach ($parts ?: [] as $part) {
			$trimmed = trim($part);
			if ($trimmed === '') {
				continue;
			}

			// Text starting with known block element - parse without soft line breaks
			if ($this->startsWithBlockElement($trimmed)) {
				$node = $this->parseBlockHtml($context, $trimmed);
			} else {
				$node = $this->parseParagraph($context, $trimmed);
				// Check if parsed content contains block-level HTML tags
				if ($this->containsBlockHtmlTag($node->content->children)) {
					$node->blockHtml = true;
				}
			}

			if ($this->isEmptyParagraph($node)) {
				continue;
			}

			$res[] = $node;
		}

		return $res;
	}


	/**
	 * Check if text starts with a known block-level HTML element.
	 */
	private function startsWithBlockElement(string $text): bool
	{
		if (!preg_match('~^<([a-z][a-z0-9]*)\b~i', $text, $m)) {
			return false;
		}
		// Not in inline elements = block element
		return !isset(Html\Element::$inlineElements[strtolower($m[1])]);
	}


	/**
	 * Parse text that starts with block HTML element (no soft line break processing).
	 */
	private function parseBlockHtml(ParseContext $context, string $text): ParagraphNode
	{
		$content = $context->parseInline($text);
		$node = new ParagraphNode($content);
		$node->blockHtml = true;
		return $node;
	}


	/**
	 * Check if content contains a block-level HtmlTagNode.
	 * Block = any HtmlTagNode that is NOT an inline element.
	 * @param  array<Texy\Node>  $content
	 */
	private function containsBlockHtmlTag(array $content): bool
	{
		foreach ($content as $node) {
			if ($node instanceof Nodes\HtmlTagNode && !$node->closing) {
				$tagName = strtolower($node->name);
				// Not an inline element = block element (includes custom elements)
				if (!isset(Html\Element::$inlineElements[$tagName])) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Check if paragraph contains only whitespace.
	 */
	private function isEmptyParagraph(ParagraphNode $node): bool
	{
		foreach ($node->content->children as $child) {
			if ($child instanceof TextNode) {
				if (trim($child->content) !== '') {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}


	private function parseParagraph(ParseContext $context, string $text): ParagraphNode
	{
		// Extract modifier from paragraph
		$modifier = null;
		if ($mx = Regexp::match($text, '~' . Patterns::MODIFIER_H . '(?= \n | \z)~sUm', captureOffset: true)) {
			[$mMod] = $mx[1];
			$text = trim(substr_replace($text, '', $mx[0][1], strlen($mx[0][0])));
			if ($text !== '') {
				$modifier = Modifier::parse($mMod);
			}
		}

		// Process line breaks
		if ($this->texy->mergeLines) {
			$text = Regexp::replace($text, '~\n\ +(?=\S)~', "\r");
			$text = Regexp::replace($text, '~\n~', ' ');
		} else {
			$text = Regexp::replace($text, '~\n~', "\r");
		}

		$content = $context->parseInline($text);

		return new ParagraphNode(
			new ContentNode($this->expandLineBreaks($content->children)),
			$modifier,
		);
	}


	/**
	 * Expand \r markers in TextNode content into LineBreakNode.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $nodes
	 * @return array<Nodes\InlineNode|Nodes\BlockNode>
	 */
	private function expandLineBreaks(array $nodes): array
	{
		$result = [];
		foreach ($nodes as $node) {
			if ($node instanceof TextNode && str_contains($node->content, "\r")) {
				foreach (explode("\r", $node->content) as $i => $part) {
					if ($i > 0) {
						$result[] = new LineBreakNode;
					}
					if ($part !== '') {
						$result[] = new TextNode($part);
					}
				}
			} elseif ($node instanceof Nodes\PhraseNode) {
				$node->content->children = $this->expandLineBreaks($node->content->children);
				$result[] = $node;
			} elseif ($node instanceof Nodes\LinkNode) {
				$node->content->children = $this->expandLineBreaks($node->content->children);
				$result[] = $node;
			} else {
				$result[] = $node;
			}
		}
		return $result;
	}


	public function solve(ParagraphNode $node, Html\Generator $generator): Html\Element
	{
		$children = $generator->renderNodes($node->content->children);

		// Block HTML content - skip <p> wrapper entirely
		if ($node->blockHtml) {
			return $this->wrapChildren($children);
		}

		$info = $this->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text → no <p> wrapper
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null) {
			return $this->wrapChildren($children);
		}

		// Only replaced content (images) → use nontextParagraph
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasReplaced']) {
			return $this->createNontextParagraph($children, $node->modifier);
		}

		// Normal paragraph
		$el = new Html\Element('p');
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $children;
		return $el;
	}


	/** @param list<Html\Element|string> $children */
	private function wrapChildren(array $children): Html\Element
	{
		$el = new Html\Element(null);
		$el->children = $children;
		return $el;
	}


	/** @param list<Html\Element|string> $children */
	private function createNontextParagraph(array $children, ?Modifier $modifier): Html\Element
	{
		$nontextParagraph = $this->texy->nontextParagraph;
		if ($nontextParagraph instanceof Html\Element) {
			$el = clone $nontextParagraph;
			$modifier?->decorate($this->texy, $el);
			$el->children = $children;
			return $el;
		}
		$el = new Html\Element($nontextParagraph);
		$modifier?->decorate($this->texy, $el);
		$el->children = $children;
		return $el;
	}


	/**
	 * Analyze content to determine what types of nodes it contains.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $content
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasOther: bool}
	 */
	private function analyzeContent(array $content): array
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
				$inlineType = Html\Element::$inlineElements[strtolower($node->name)] ?? null;
				if ($inlineType === 1) {
					$hasReplaced = true;    // replaced element (img, br, input, ...)
				} else {
					$hasMarkup = true;      // inline markup or block element
				}

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
}
