<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HtmlCommentNode;
use Texy\Nodes\HtmlTagNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use Texy\Syntax;
use function explode, htmlspecialchars, implode, in_array, is_array, is_string, preg_replace, str_contains, str_ends_with, str_replace, strlen, strtolower, strtr, substr, trim;
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
			Syntax::HtmlTag,
		);

		$this->texy->registerLinePattern(
			fn(?ParseContext $context, array $matches) => new HtmlCommentNode($matches[1]),
			'~
				<!--
				( [^' . Patterns::MARK . ']*? )
				-->
			~is',
			Syntax::HtmlComment,
		);
	}


	/**
	 * Parses <tag attr="...">
	 * @param  array<?string>  $matches
	 */
	public function parseTag(?ParseContext $context, array $matches): ?HtmlTagNode
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
		);
	}


	public function solveTag(HtmlTagNode $node, Html\Generator $generator): string
	{
		$tagName = strtolower($node->name);
		$attrs = $node->attributes;

		// Check if tag is allowed
		$allowedTags = $this->texy->allowedTags;
		if (!$allowedTags) {
			// All tags are disabled
			return $this->escapeTag($node);
		}
		if (is_array($allowedTags) && !isset($allowedTags[$tagName])) {
			// Tag not in allowed list
			return $this->escapeTag($node);
		}

		// Validate tag - reject if validation fails
		$validation = $this->validateTag($tagName, $attrs, $node->closing);
		if ($validation === false || $validation === 'drop') {
			// Invalid tag - escape as text (shows original tag in output)
			return $this->escapeTag($node);
		}

		// Add rel="nofollow" for external links when forceNoFollow is enabled
		if ($tagName === 'a' && !$node->closing && isset($attrs['href']) && is_string($attrs['href'])) {
			if ($this->texy->linkModule->forceNoFollow && str_contains($attrs['href'], '//')) {
				$existingRel = isset($attrs['rel']) && is_string($attrs['rel']) ? $attrs['rel'] : '';
				$relParts = $existingRel ? explode(' ', $existingRel) : [];
				if (!in_array('nofollow', $relParts, true)) {
					$relParts[] = 'nofollow';
				}
				$attrs['rel'] = implode(' ', $relParts);
			}
		}

		// Determine content type based on HtmlElement::$inlineElements
		// Value 0 = inline markup, 1 = inline replaced, not present = block
		$inlineType = Html\Element::$inlineElements[$tagName] ?? null;
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

		$el = new Html\Element($tagName, $attrs);
		$html = $el->startTag();

		return $this->texy->protect($html, $type);
	}


	/**
	 * Validate HTML tag.
	 * Returns: true = valid, false = escape as text, 'drop' = remove entirely
	 * @param  array<string, string|bool>  $attrs
	 */
	private function validateTag(string $tagName, array $attrs, bool $closing): bool|string
	{
		// <a> requires href, name, or id
		if ($tagName === 'a' && !$closing) {
			if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) {
				return false;
			}
			// Validate href URL scheme (security: filter javascript: etc.)
			if (isset($attrs['href']) && is_string($attrs['href'])) {
				if (!$this->texy->checkURL($attrs['href'], $this->texy::FILTER_ANCHOR)) {
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
			if (is_string($attrs['src']) && !$this->texy->checkURL($attrs['src'], $this->texy::FILTER_IMAGE)) {
				return 'drop'; // XSS protection - drop dangerous URLs entirely
			}
		}

		return true;
	}


	/**
	 * Escape tag as text (when validation fails).
	 * Only escapes < and > so quotes remain for typography processing.
	 */
	private function escapeTag(HtmlTagNode $node): string
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


	public function solveComment(HtmlCommentNode $node, Html\Generator $generator): string
	{
		if (!$this->passComment) {
			return '';
		}

		// Sanitize comment content (security: prevent nested comments)
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
				$res[$key] = trim(Texy\Helpers::unescapeHtml(substr($value, 1, -1)));
			} else {
				$res[$key] = trim(Texy\Helpers::unescapeHtml($value));
			}
		}

		return $res;
	}
}
