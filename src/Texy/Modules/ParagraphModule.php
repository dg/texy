<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
		foreach ($parts as [$part, $partOffset]) {
			$partOffset += $baseOffset;
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
		return !isset(Html\Schema::inlineElements()[strtolower($m[1])]);
	}


	/**
	 * Parse text that starts with block HTML element (no soft line break processing).
	 */
	private function parseBlockHtml(ParseContext $context, string $text, int $baseOffset = 0): ParagraphNode
	{
		$content = $context->parseInline($text, $baseOffset);
		$node = new ParagraphNode($content, range: new Texy\Range($baseOffset, strlen($text)));
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
				if (!isset(Html\Schema::inlineElements()[$tagName])) {
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


	private function parseParagraph(ParseContext $context, string $text, int $baseOffset = 0): ParagraphNode
	{
		$lines = Texy\OffsetMap::linesOf($text, $baseOffset);

		// Extract modifier from paragraph; MODIFIER_H cannot span lines, so the
		// removal only shortens the end of a single line
		$modifier = null;
		if ($mx = Regexp::match($text, '~' . Patterns::ModifierHAlign . '(?= \n | \z)~sUmx', captureOffset: true)) {
			/** @var array{array{string, int}, array{string, int}} $mx */
			[$mMod] = $mx[1];
			$cutOffset = $baseOffset + $mx[0][1]; // full match incl. leading spaces and dot
			foreach ($lines as $i => $line) {
				$local = $cutOffset - $line['offset'];
				if ($local >= 0 && $local <= strlen($line['content'])) {
					$lines[$i]['content'] = rtrim(substr($line['content'], 0, $local));
				}
			}
			$modifier = Modifier::parse($mMod, $baseOffset + $mx[1][1]);
		}

		// the former trim(): drop blank edge lines and edge whitespace
		while ($lines && trim($lines[0]['content']) === '') {
			array_shift($lines);
		}
		while ($lines && trim($lines[count($lines) - 1]['content']) === '') {
			array_pop($lines);
		}
		if (!$lines) {
			return new ParagraphNode(new ContentNode, range: new Texy\Range($baseOffset, strlen($text)));
		}
		$trimmed = ltrim($lines[0]['content'], " \t");
		$lines[0]['offset'] += strlen($lines[0]['content']) - strlen($trimmed);
		$lines[0]['content'] = $trimmed;
		$last = count($lines) - 1;
		$lines[$last]['content'] = rtrim($lines[$last]['content'], " \t");

		// Join lines with single-character separators: ' ' for merged lines,
		// "\r" for hard breaks. The separators have the same length as the "\n"
		// they replace, so the per-line offset map stays valid.
		$joined = '';
		$mapLines = [];
		foreach ($lines as $i => $line) {
			$content = $line['content'];
			$offset = $line['offset'];
			if ($i > 0) {
				$strip = strspn($content, ' ');
				if (!$this->texy->mergeLines) {
					$joined .= "\r";
				} elseif ($strip > 0 && ($content[$strip] ?? "\t") !== "\t") {
					// space-indented continuation line (the former `\n\ +(?=\S)` rule):
					// hard break, indentation stripped
					$joined .= "\r";
					$content = substr($content, $strip);
					$offset += $strip;
				} else {
					$joined .= ' ';
				}
			}
			$mapLines[] = ['content' => $content, 'offset' => $offset];
			$joined .= $content;
		}

		// hard breaks are expanded while ranges are still local (the split
		// arithmetic must not skip the stripped continuation indentation),
		// then the line map translates everything to source coordinates
		$content = $context->parseInline($joined);
		$node = new ContentNode($this->expandLineBreaks($content->children));
		Texy\OffsetMap::fromLines($mapLines)->applyTo($node);

		return new ParagraphNode($node, $modifier, new Texy\Range($baseOffset, strlen($text)));
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
				// sub-ranges are computed from decoded content lengths, so they are
				// exact unless the text contained entities (ranges are best-effort)
				$offset = $node->range?->offset;
				foreach (explode("\r", $node->text) as $i => $part) {
					if ($i > 0) {
						$result[] = new LineBreakNode($offset === null ? null : new Texy\Range($offset - 1, 1));
					}
					if ($part !== '') {
						$result[] = new TextNode($part, $offset === null ? $node->range : new Texy\Range($offset, strlen($part)));
					}
					$offset = $offset === null ? null : $offset + strlen($part) + 1;
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
