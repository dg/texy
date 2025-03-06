<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Nodes, Patterns, Regexp};
use Texy\Nodes\{ContentNode, LineBreakNode, ParagraphNode, TextNode};
use Texy\Output\Html;
use Texy\Parsing\ParseContext;
use function is_string, strlen;


/**
 * Processes paragraphs and handles line breaks.
 */
final class ParagraphModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->setGapHandler($this->parseText(...));
		$texy->htmlOutput->addRenderer($this->render(...));
		$texy->htmlOutput->addRenderer(
			fn(LineBreakNode $node, Html\Renderer $renderer) => $renderer->protect('<br>', $renderer::ContentReplaced),
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
		foreach ($parts as $part) {
			$trimmed = trim($part);
			if ($trimmed === '') {
				continue;
			}

			// Text starting with block element - parse without soft line breaks, unless the policy rejects it
			$node = $this->startsWithBlockElement($trimmed)
				? $this->parseBlockHtml($context, $trimmed)
				: null;
			if ($node === null || !$this->containsBlockHtmlTag($node->content->children)) {
				$node = $this->parseParagraph($context, $trimmed);
				$node->blockHtml = $this->containsBlockHtmlTag($node->content->children);
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
		return !isset(Html\Schema::inlineElements()[strtolower($m[1])]);
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
	 * Check if content contains a block-level HtmlTagNode the policy accepts.
	 * Block = any HtmlTagNode that is NOT an inline element.
	 * @param  array<Texy\Node>  $content
	 */
	private function containsBlockHtmlTag(array $content): bool
	{
		foreach ($content as $node) {
			if ($node instanceof Nodes\HtmlTagNode && !$node->closing) {
				$tagName = strtolower($node->name);
				// Not an inline element = block element (includes custom elements)
				if (
					!isset(Html\Schema::inlineElements()[$tagName])
					&& $this->texy->htmlModule->isTagAcceptable($tagName, $node->attributes, closing: false)
				) {
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
				if (trim($child->text) !== '') {
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
		if ($mx = Regexp::match($text, '~' . Patterns::MODIFIER_H . '(?= \n | \z)~sUmx', captureOffset: true)) {
			/** @var array{array{string, int}, array{string, int}} $mx */
			[$mMod] = $mx[1];
			$text = trim(substr_replace($text, '', $mx[0][1], strlen($mx[0][0])));
			if ($text !== '') {
				$modifier = Modifier::parse($mMod);
			}
		}

		// Process line breaks: a space-indented continuation line is a hard break
		if ($this->texy->mergeLines) {
			$text = Regexp::replace($text, '~\n\ +(?=\S)~', "\r");
			$text = Regexp::replace($text, '~\n~', ' ');
		} else {
			$text = Regexp::replace($text, '~\n~', "\r");
		}

		$content = $context->parseInline($text);
		return new ParagraphNode(new ContentNode($this->expandLineBreaks($content->children)), $modifier);
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
			if ($node instanceof TextNode && str_contains($node->text, "\r")) {
				foreach (explode("\r", $node->text) as $i => $part) {
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


	public function render(ParagraphNode $node, Html\Renderer $renderer): Html\Element
	{
		$children = $renderer->renderNodes($node->content->children);

		// Block HTML content - skip <p> wrapper entirely
		if ($node->blockHtml) {
			return $renderer->wrapChildren($children);
		}

		// Whole content rendered to nothing (consumed directives, comments) → no paragraph at all
		if ($node->modifier === null && array_all($children, fn($child) => is_string($child) && trim($child) === '')) {
			return $renderer->wrapChildren([]);
		}

		$info = $this->analyzeContent($node->content->children);

		// Only markup (HTML tags/comments) without text → no <p> wrapper
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasMarkup'] && $node->modifier === null) {
			return $renderer->wrapChildren($children);
		}

		// Only replaced content (images) → use nontextParagraph
		if (!$info['hasText'] && !$info['hasOther'] && $info['hasReplaced']) {
			$el = new Html\Element($this->texy->nontextParagraph);
			$renderer->decorateElement($node->modifier, $el);
			$el->children = $children;
			return $el;
		}

		// Normal paragraph
		$el = new Html\Element('p');
		$renderer->decorateElement($node->modifier, $el);
		$el->children = $children;
		return $el;
	}


	/**
	 * Analyze paragraph content to determine what types of nodes it contains.
	 * @param  array<Nodes\BlockNode|Nodes\InlineNode>  $content
	 * @return array{hasText: bool, hasReplaced: bool, hasMarkup: bool, hasOther: bool}
	 */
	private function analyzeContent(array $content): array
	{
		$hasText = false;
		$hasReplaced = false;
		$hasMarkup = false;
		$hasOther = false;

		foreach ($content as $node) {
			if ($node instanceof TextNode) {
				$hasText = $hasText || trim($node->text) !== '';

			} elseif ($node instanceof Nodes\ImageNode) {
				$hasReplaced = true;

			} elseif ($node instanceof Nodes\HtmlCommentNode) {
				$hasMarkup = true;

			} elseif ($node instanceof Nodes\HtmlTagNode) {
				$inlineType = Html\Schema::inlineElements()[strtolower($node->name)] ?? null;
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
}
