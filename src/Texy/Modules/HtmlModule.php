<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HtmlElement;
use Texy\Patterns;
use Texy\Regexp;


/**
 * Html tags module.
 */
final class HtmlModule extends Texy\Module
{
	/** pass HTML comments to output? */
	public bool $passComment = true;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('htmlComment', $this->commentToElement(...));
		$texy->addHandler('htmlTag', $this->tagToElement(...));

		$texy->registerLinePattern(
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

		$texy->registerLinePattern(
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
	 * Callback for: <!-- comment -->.
	 */
	public function parseComment(Texy\LineParser $parser, array $matches): HtmlElement|string|null
	{
		[, $mComment] = $matches;
		return $this->texy->invokeAroundHandlers('htmlComment', $parser, [$mComment]);
	}


	/**
	 * Callback for: <tag attr="...">.
	 */
	public function parseTag(Texy\LineParser $parser, array $matches): HtmlElement|string|null
	{
		[, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;
		// [1] => /
		// [2] => tag
		// [3] => attributes
		// [4] => /

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && str_ends_with($mAttr, '/')) { // uvizlo v $mAttr?
			$mAttr = substr($mAttr, 0, -1);
			$isEmpty = true;
		}

		// error - can't close empty element
		if ($isEmpty && !$isStart) {
			return null;
		}

		// error - end element with atttrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return null;
		}

		$el = new HtmlElement($mTag);
		if ($isStart) {
			$el->attrs = $this->parseAttributes($mAttr);
		}

		$res = $this->texy->invokeAroundHandlers('htmlTag', $parser, [$el, $isStart, $isEmpty]);

		if ($res instanceof HtmlElement) {
			return $this->texy->protect($isStart ? $res->startTag() : $res->endTag(), $res->getContentType());
		}

		return $res;
	}


	public function tagToElement(
		Texy\HandlerInvocation $invocation,
		HtmlElement $el,
		bool $isStart,
		?bool $forceEmpty = null,
	): HtmlElement|string|null
	{
		$texy = $this->texy;

		// tag & attibutes
		$allowedTags = $texy->allowedTags; // speed-up
		if (!$allowedTags) {
			return null; // all tags are disabled
		}

		// convert case
		$name = $el->getName();
		$lower = strtolower($name);
		if (isset($texy->getDTD()[$lower]) || $name === strtoupper($name)) {
			// complete UPPER convert to lower
			$name = $lower;
			$el->setName($name);
		}

		if (is_array($allowedTags)) {
			if (!isset($allowedTags[$name])) {
				return null;
			}
		} else { // allowedTags === Texy\Texy::ALL
			if ($forceEmpty) {
				$el->setName($name, true);
			}
		}

		// end tag? we are finished
		if (!$isStart) {
			return $el;
		}

		$this->applyAttrs($el->attrs, is_array($allowedTags) ? $allowedTags[$name] : $texy::ALL);
		$this->applyClasses($el->attrs, $texy->getAllowedProps()[0]);
		$this->applyStyles($el->attrs, $texy->getAllowedProps()[1]);
		if (!$this->validateAttrs($el, $texy)) {
			return null;
		}

		$el->validateAttrs($texy->getDTD());

		return $el;
	}


	public function commentToElement(Texy\HandlerInvocation $invocation, string $content): string
	{
		if (!$this->passComment) {
			return '';
		}

		// sanitize comment
		$content = Regexp::replace($content, '~-{2,}~', ' - ');
		$content = trim($content, '-');

		return $this->texy->protect('<!--' . $content . '-->', Texy\Texy::CONTENT_MARKUP);
	}


	private function applyAttrs(&$attrs, $allowedAttrs): void
	{
		if (!$allowedAttrs) {
			$attrs = [];

		} elseif (is_array($allowedAttrs)) {
			// skip disabled
			$allowedAttrs = array_flip($allowedAttrs);
			foreach ($attrs as $key => $foo) {
				if (!isset($allowedAttrs[$key])) {
					unset($attrs[$key]);
				}
			}
		}
	}


	private function applyClasses(&$attrs, $allowedClasses): void
	{
		if (!isset($attrs['class'])) {
		} elseif (is_array($allowedClasses)) {
			$attrs['class'] = explode(' ', $attrs['class']);
			foreach ($attrs['class'] as $key => $value) {
				if (!isset($allowedClasses[$value])) {
					unset($attrs['class'][$key]); // id & class are case-sensitive
				}
			}
		} elseif ($allowedClasses !== Texy\Texy::ALL) {
			$attrs['class'] = null;
		}

		if (!isset($attrs['id'])) {
		} elseif (is_array($allowedClasses)) {
			if (!isset($allowedClasses['#' . $attrs['id']])) {
				$attrs['id'] = null;
			}
		} elseif ($allowedClasses !== Texy\Texy::ALL) {
			$attrs['id'] = null;
		}
	}


	private function applyStyles(&$attrs, $allowedStyles): void
	{
		if (!isset($attrs['style'])) {
		} elseif (is_array($allowedStyles)) {
			$tmp = explode(';', $attrs['style']);
			$attrs['style'] = null;
			foreach ($tmp as $value) {
				$pair = explode(':', $value, 2);
				$prop = trim($pair[0]);
				if (isset($pair[1], $allowedStyles[strtolower($prop)])) { // CSS is case-insensitive
					$attrs['style'][$prop] = $pair[1];
				}
			}
		} elseif ($allowedStyles !== Texy\Texy::ALL) {
			$attrs['style'] = null;
		}
	}


	private function validateAttrs(HtmlElement $el, Texy\Texy $texy): bool
	{
		foreach (['src', 'href', 'name', 'id'] as $attr) {
			if (isset($el->attrs[$attr])) {
				$el->attrs[$attr] = is_string($el->attrs[$attr])
					? trim($el->attrs[$attr])
					: '';
				if ($el->attrs[$attr] === '') {
					unset($el->attrs[$attr]);
				}
			}
		}

		$name = $el->getName();
		if ($name === 'img') {
			if (!isset($el->attrs['src']) || !$texy->checkURL($el->attrs['src'], $texy::FILTER_IMAGE)) {
				return false;
			}

			$texy->summary['images'][] = $el->attrs['src'];

		} elseif ($name === 'a') {
			if (!isset($el->attrs['href']) && !isset($el->attrs['name']) && !isset($el->attrs['id'])) {
				return false;
			}

			if (isset($el->attrs['href'])) {
				if ($texy->linkModule->forceNoFollow && str_contains($el->attrs['href'], '//')) {
					if (isset($el->attrs['rel'])) {
						$el->attrs['rel'] = (array) $el->attrs['rel'];
					}

					$el->attrs['rel'][] = 'nofollow';
				}

				if (!$texy->checkURL($el->attrs['href'], $texy::FILTER_ANCHOR)) {
					return false;
				}

				$texy->summary['links'][] = $el->attrs['href'];
			}
		} elseif (Regexp::match($name, '~^h[1-6]~i')) {
			$texy->headingModule->TOC[] = [
				'el' => $el,
				'level' => (int) substr($name, 1),
				'type' => 'html',
			];
		}

		return true;
	}


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
