<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use JetBrains\PhpStorm\Language;
use Texy\Nodes\BlockNode;
use Texy\Nodes\LineBreakNode;
use Texy\Nodes\ParagraphNode;
use Texy\Nodes\TextNode;
use function count, explode, max, preg_match, strlen, strtolower, substr, trim, usort;


/**
 * Parses block structures (paragraphs, headings, lists, tables, etc.).
 */
class BlockParser
{
	private string $text;
	private int $offset;


	public function __construct(
		protected Texy $texy,
		/** @var array<string, array{handler: \Closure(self, array<string>, string, array<int|string, int|null>): ?BlockNode, pattern: string}> */
		public array $patterns,
	) {
	}


	/**
	 * Match current line against RE.
	 * If successful, increments current position and returns true.
	 * @param  ?array<string>  $matches
	 * @param-out array<string> $matches
	 */
	public function next(
		#[Language('PhpRegExpXTCommentMode')]
		string $pattern,
		?array &$matches,
	): bool
	{
		if ($this->offset > strlen($this->text)) {
			return false;
		}

		$matches = [];
		/** @var ?array<array{string, int}> $m */
		$m = Regexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			captureOffset: true,
			offset: $this->offset,
		);

		if ($m) {
			$this->offset += strlen($m[0][0]) + 1; // 1 = "\n"
			foreach ($m as $key => $value) {
				$matches[$key] = $value[0];
			}

			return true;
		}

		return false;
	}


	/**
	 * Moves position back by specified number of lines.
	 */
	public function moveBackward(int $linesCount = 1): void
	{
		while (--$this->offset > 0) {
			if ($this->text[$this->offset - 1] === "\n") {
				$linesCount--;
				if ($linesCount < 1) {
					break;
				}
			}
		}

		$this->offset = max($this->offset, 0);
	}


	public function parse(string $text): array
	{
		$this->text = $text;
		$this->offset = 0;
		$matches = $this->match($text);
		$matches[] = [strlen($text), null, null, 0, null]; // terminal sentinel
		$cursor = 0;
		$res = [];

		do {
			do {
				[$mOffset, $mName, $mMatches, , $mOffsets] = $matches[$cursor];
				$cursor++;
				if ($mName === null || $mOffset >= $this->offset) {
					break;
				}
			} while (true);

			// between-matches content → paragraphs (split by blank lines)
			if ($mOffset > $this->offset) {
				$s = substr($text, $this->offset, $mOffset - $this->offset);
				$res = array_merge($res, $this->parseParagraphs($s));
			}

			if ($mName === null) {
				break; // finito
			}

			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			$handler = $this->patterns[$mName]['handler'];
			$node = $handler($this, $mMatches, $mName, $mOffsets);

			if ($node === null || $this->offset <= $mOffset) {
				// handler rejects text
				$this->offset = $mOffset;
				continue;
			}

			$res[] = $node;

		} while (true);

		return $res;
	}


	/** @return list<array{int, ?string, ?array<int|string, string|null>, int, ?array<int|string, int|null>}> */
	private function match(string $text): array
	{
		$matches = [];
		$priority = 0;

		foreach ($this->patterns as $name => $pattern) {
			$ms = Regexp::matchAll(
				$text,
				$pattern['pattern'],
				captureOffset: true,
			);

			foreach ($ms as $m) {
				$offset = $m[0][1];
				$offsets = [];
				foreach ($m as $k => $v) {
					$offsets[$k] = $v[1] >= 0 ? $v[1] : null;
					$m[$k] = $v[0];
				}

				$matches[] = [$offset, $name, $m, $priority, $offsets];
			}

			$priority++;
		}

		usort($matches, function ($a, $b): int {
			if ($a[0] === $b[0]) {
				return $a[3] < $b[3] ? -1 : 1;
			}

			return $a[0] < $b[0] ? -1 : 1;
		});

		return $matches;
	}


	/**
	 * Split text by blank lines and create paragraphs.
	 * Handles HTML block elements specially - they don't get wrapped in <p>.
	 */
	private function parseParagraphs(string $text): array
	{
		// Split by two or more newlines (blank line)
		$parts = Regexp::split($text, '~(\n{2,})~', skipEmpty: true);
		$res = [];
		foreach ($parts ?: [] as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}

			// Check if this part starts with a block-level HTML tag
			if ($this->startsWithBlockHtmlTag($part)) {
				$node = $this->parseHtmlBlock($part);
			} else {
				$node = $this->parseParagraph($part);
			}

			// Skip empty paragraphs (only whitespace content)
			if ($node instanceof ParagraphNode && $this->isEmptyParagraph($node)) {
				continue;
			}

			$res[] = $node;
		}

		return $res;
	}


	/**
	 * Check if text starts with a known block-level HTML tag.
	 */
	private function startsWithBlockHtmlTag(string $text): bool
	{
		// Match opening HTML tag at start
		if (!preg_match('~^<([a-z][a-z0-9]*)\b~i', $text, $m)) {
			return false;
		}

		$tagName = strtolower($m[1]);
		return !isset(HtmlElement::$inlineElements[$tagName]);
	}


	/**
	 * Parse text that starts with block HTML tag.
	 * Returns ParagraphNode with blockHtml flag for special handling.
	 */
	private function parseHtmlBlock(string $text): ParagraphNode
	{
		$content = $this->texy->createInlineParser()->parse($text);

		// Create paragraph without line break processing
		// The block HTML content should preserve its structure
		$node = new ParagraphNode($content);
		$node->blockHtml = true;
		return $node;
	}


	/**
	 * Check if paragraph contains only whitespace.
	 */
	private function isEmptyParagraph(ParagraphNode $node): bool
	{
		foreach ($node->content as $child) {
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


	private function parseParagraph(string $text): ParagraphNode
	{
		$inlineParser = $this->texy->createInlineParser();

		// Extract modifier from paragraph (can appear at end of any line or at end of text)
		$modifier = null;
		if ($mx = Regexp::match($text, '~' . Patterns::MODIFIER_H . '(?= \n | \z)~sUm', captureOffset: true)) {
			[$mMod] = $mx[1];
			$text = trim(substr_replace($text, '', $mx[0][1], strlen($mx[0][0])));
			if ($text !== '') {
				$modifier = Modifier::parse($mMod);
			}
		}

		// Process line breaks
		// mergeLines: true (default) - line break only when next line starts with space
		// mergeLines: false - every \n is a line break
		if ($this->texy->mergeLines) {
			// Mark line breaks (newline followed by space) with special char
			$text = Regexp::replace($text, '~\n\ +(?=\S)~', "\r");
			// Merge other newlines into spaces
			$text = Regexp::replace($text, '~\n~', ' ');
		} else {
			// Every newline is a line break
			$text = Regexp::replace($text, '~\n~', "\r");
		}

		// Split on line break markers
		$parts = explode("\r", $text);
		if (count($parts) === 1) {
			// No line breaks
			$content = $inlineParser->parse($text);
			return new ParagraphNode($content, $modifier);
		}

		// Multiple parts - insert LineBreakNode between them
		$content = [];
		foreach ($parts as $i => $part) {
			if ($i > 0) {
				$content[] = new LineBreakNode;
			}
			$partNodes = $inlineParser->parse($part);
			foreach ($partNodes as $node) {
				$content[] = $node;
			}
		}

		return new ParagraphNode($content, $modifier);
	}
}
