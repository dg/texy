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


/**
 * Html tags module.
 */
final class HtmlModule extends Texy\Module
{
	/** @var bool   pass HTML comments to output? */
	public $passComment = true;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('htmlComment', [$this, 'solveComment']);
		$texy->addHandler('htmlTag', [$this, 'solveTag']);

		$texy->registerLinePattern(
			[$this, 'patternTag'],
			'#<(/?)([a-z][a-z0-9_:-]{0,50})((?:\s++[a-z0-9\_:-]++|=\s*+"[^"' . Patterns::MARK . ']*+"|=\s*+\'[^\'' . Patterns::MARK . ']*+\'|=[^\s>' . Patterns::MARK . ']++)*)\s*+(/?)>#isu',
			'html/tag'
		);

		$texy->registerLinePattern(
			[$this, 'patternComment'],
			'#<!--([^' . Patterns::MARK . ']*?)-->#is',
			'html/comment'
		);
	}


	/**
	 * Callback for: <!-- comment -->.
	 * @return HtmlElement|string|null
	 */
	public function patternComment(Texy\LineParser $parser, array $matches)
	{
		[, $mComment] = $matches;
		return $this->texy->invokeAroundHandlers('htmlComment', $parser, [$mComment]);
	}


	/**
	 * Callback for: <tag attr="...">.
	 * @return HtmlElement|string|null
	 */
	public function patternTag(Texy\LineParser $parser, array $matches)
	{
		[, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;
		// [1] => /
		// [2] => tag
		// [3] => attributes
		// [4] => /

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && substr($mAttr, -1) === '/') { // uvizlo v $mAttr?
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


	/**
	 * Finish invocation.
	 * @return HtmlElement|string|null
	 */
	public function solveTag(Texy\HandlerInvocation $invocation, HtmlElement $el, bool $isStart, bool $forceEmpty = null)
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


	/**
	 * Finish invocation.
	 */
	public function solveComment(Texy\HandlerInvocation $invocation, string $content): string
	{
		if (!$this->passComment) {
			return '';
		}

		// sanitize comment
		$content = Texy\Regexp::replace($content, '#-{2,}#', ' - ');
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
				if (isset($pair[1]) && isset($allowedStyles[strtolower($prop)])) { // CSS is case-insensitive
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
				$el->attrs[$attr] = is_string($el->attrs[$attr]) ? trim($el->attrs[$attr]) : '';
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
				if ($texy->linkModule->forceNoFollow && strpos($el->attrs['href'], '//') !== false) {
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

		} elseif (preg_match('#^h[1-6]#i', $name)) {
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
		$matches = $res = [];
		preg_match_all(
			'#([a-z0-9\_:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#isu',
			$attrs,
			$matches,
			PREG_SET_ORDER
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
