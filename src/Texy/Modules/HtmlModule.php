<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\InlineParser;
use Texy\Nodes\HtmlCommentNode;
use Texy\Nodes\HtmlTagNode;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use Texy\Regexp;
use function htmlspecialchars, preg_replace, str_ends_with, strlen, strtolower, strtr, substr, trim;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Processes HTML tags and comments in input text.
 */
final class HtmlModule extends Texy\Module
{
	/** pass HTML comments to output? */
	public bool $passComment = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solveTag(...));
		$texy->htmlGenerator->registerHandler($this->solveComment(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
			$this->parseTag(...),
			'~
				< (/?)                          # tag begin
				([a-z][a-z0-9_:-]{0,50})        # tag name
				(
					(?:
						\s++ [a-z0-9_:-]++ |   # attribute name
						= \s*+ " [^"' . Patterns::MARK . ']*+ " |     # attribute value in double quotes
						= \s*+ \' [^\'' . Patterns::MARK . ']*+ \' |  # attribute value in single quotes
						= [^\s>' . Patterns::MARK . ']++              # attribute value without quotes
					)*
				)
				\s*+
				(/?)                             # self-closing slash
				>
			~is',
			'html/tag',
		);

		$this->texy->registerLinePattern(
			$this->parseComment(...),
			'~
				<!--
				( [^' . Patterns::MARK . ']*? )
				-->
			~is',
			'html/comment',
		);
	}


	/**
	 * Parses <!-- comment -->
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseComment(InlineParser $parser, array $matches, string $name, array $offsets): HtmlCommentNode
	{
		[, $mContent] = $matches;
		return new HtmlCommentNode($mContent, new Position($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses <tag attr="...">
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseTag(InlineParser $parser, array $matches, string $name, array $offsets): ?HtmlTagNode
	{
		[, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && str_ends_with($mAttr, '/')) {
			$mAttr = substr($mAttr, 0, -1);
			$isEmpty = true;
		}

		// error - can't close empty element
		if ($isEmpty && !$isStart) {
			return null;
		}

		// error - end element with attrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return null;
		}

		return new HtmlTagNode(
			$mTag,
			$isStart ? $this->parseAttributes($mAttr) : [],
			closing: !$isStart,
			selfClosing: $isEmpty,
			position: new Position($offsets[0], strlen($matches[0])),
		);
	}


	public function solveTag(HtmlTagNode $node, Generator $generator): string
	{
		$tagName = strtolower($node->name);

		// Validate tag - reject if validation fails
		if (!$this->validateTag($tagName, $node->attributes, $node->closing)) {
			return $this->escapeTag($node);
		}

		// Determine content type based on HtmlElement::$inlineElements
		// Value 0 = inline markup, 1 = inline replaced, not present = block
		$inlineType = Texy\HtmlElement::$inlineElements[$tagName] ?? null;
		if ($inlineType === null) {
			$type = $this->texy::CONTENT_BLOCK;
		} elseif ($inlineType === 1) {
			$type = $this->texy::CONTENT_REPLACED;
		} else {
			$type = $this->texy::CONTENT_MARKUP;
		}

		if ($node->closing) {
			$html = '</' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '>';
			return $this->texy->protect($html, $type);
		}

		$html = '<' . htmlspecialchars($tagName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$html .= $generator->generateAttrs($node->attributes);
		$html .= '>';

		return $this->texy->protect($html, $type);
	}


	/**
	 * Validate HTML tag - returns false if tag should be rejected.
	 * @param  array<string, string|bool>  $attrs
	 */
	private function validateTag(string $tagName, array $attrs, bool $closing): bool
	{
		// <a> requires href, name, or id
		if ($tagName === 'a' && !$closing) {
			if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
				return false;
			}
		}

		// <img> requires src
		if ($tagName === 'img') {
			if (!isset($attrs['src']) || (is_string($attrs['src']) && trim($attrs['src']) === '')) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Escape tag as text (when validation fails).
	 */
	private function escapeTag(HtmlTagNode $node): string
	{
		// Reconstruct the original tag text and escape it
		$tag = '<';
		if ($node->closing) {
			$tag .= '/';
		}
		$tag .= $node->name;
		foreach ($node->attributes as $name => $value) {
			$tag .= ' ' . $name;
			if ($value !== true) {
				$tag .= '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
			}
		}
		if ($node->selfClosing) {
			$tag .= '/';
		}
		$tag .= '>';

		return htmlspecialchars($tag, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}


	public function solveComment(HtmlCommentNode $node, Generator $generator): string
	{
		// Sanitize comment content
		$content = preg_replace('~-{2,}~', ' - ', $node->content);
		$content = trim($content, '-');
		return $this->texy->protect('<!--' . $content . '-->', $this->texy::CONTENT_MARKUP);
	}


	/** @return array<string, string|bool> */
	private function parseAttributes(string $attrs): array
	{
		$res = [];
		$matches = Regexp::matchAll(
			$attrs,
			<<<'X'
				~
				([a-z0-9_:-]+)                 # attribute name
				\s*
				(?:
					= \s*                      # equals sign
					(
						' [^']* ' |            # single quoted value
						" [^"]* " |            # double quoted value
						[^'"\s]+               # unquoted value
					)
				)?
				~is
				X,
		);

		foreach ($matches as $m) {
			$key = strtolower($m[1]);
			$value = $m[2];
			if ($value == null) {
				$res[$key] = true;
			} elseif ($value[0] === '\'' || $value[0] === '"') {
				$res[$key] = Texy\Helpers::unescapeHtml(substr($value, 1, -1));
			} else {
				$res[$key] = Texy\Helpers::unescapeHtml($value);
			}
		}

		return $res;
	}
}
