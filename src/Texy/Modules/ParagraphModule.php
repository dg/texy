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
	}


	/**
	 * Parse text into paragraphs (split by blank lines).
	 * @return array<ParagraphNode>
	 */
	public function parseText(ParseContext $context, string $text, int $baseOffset = 0): array
	{
		$parts = Regexp::split($text, '~(\n{2,})~', captureOffset: true, skipEmpty: true);
		$res = [];
		foreach ($parts ?: [] as $partInfo) {
			// With captureOffset, each part is [content, offset]
			if (is_array($partInfo)) {
				[$part, $partOffset] = $partInfo;
				$partOffset += $baseOffset;
			} else {
				$part = $partInfo;
				$partOffset = $baseOffset;
			}

			$trimmed = trim($part);
			if ($trimmed === '') {
				continue;
			}

			// Calculate offset after leading whitespace trim
			$leadingTrim = strlen($part) - strlen(ltrim($part));
			$contentOffset = $partOffset + $leadingTrim;

			// Text starting with known block element - parse without soft line breaks
			if ($this->startsWithBlockElement($trimmed)) {
				$node = $this->parseBlockHtml($context, $trimmed, $contentOffset);
			} else {
				$node = $this->parseParagraph($context, $trimmed, $contentOffset);
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
	private function parseBlockHtml(ParseContext $context, string $text, int $baseOffset = 0): ParagraphNode
	{
		$content = $context->parseInline($text, $baseOffset);
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


	private function parseParagraph(ParseContext $context, string $text, int $baseOffset = 0): ParagraphNode
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

		// Process line breaks - note: this changes text length, positions become approximate
		if ($this->texy->mergeLines) {
			$text = Regexp::replace($text, '~\n\ +(?=\S)~', "\r");
			$text = Regexp::replace($text, '~\n~', ' ');
		} else {
			$text = Regexp::replace($text, '~\n~', "\r");
		}

		$content = $context->parseInline($text, $baseOffset);

		return new ParagraphNode(
			new ContentNode($this->expandLineBreaks($content->children)),
			$modifier,
		);
	}


	/**
	 * Expand \r markers in TextNode content into LineBreakNode.
	 * @param  array<Texy\Node>  $nodes
	 * @return array<Texy\Node>
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
						$result[] = new TextNode($part, $node->position);
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
}
