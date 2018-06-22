<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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


	public function __construct($texy)
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
	 * @return HtmlElement|string|false
	 */
	public function patternComment(Texy\LineParser $parser, array $matches)
	{
		list(, $mComment) = $matches;
		return $this->texy->invokeAroundHandlers('htmlComment', $parser, [$mComment]);
	}


	/**
	 * Callback for: <tag attr="...">.
	 * @return HtmlElement|string|false
	 */
	public function patternTag(Texy\LineParser $parser, array $matches)
	{
		list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
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
			return false;
		}

		// error - end element with atttrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return false;
		}

		$el = new HtmlElement($mTag);

		if ($isStart) {
			// parse attributes
			$matches2 = null;
			preg_match_all(
				'#([a-z0-9\_:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#isu',
				$mAttr,
				$matches2,
				PREG_SET_ORDER
			);

			foreach ($matches2 as $m) {
				$key = strtolower($m[1]);
				$value = $m[2];
				if ($value == null) {
					$el->attrs[$key] = true;
				} elseif ($value{0} === '\'' || $value{0} === '"') {
					$el->attrs[$key] = html_entity_decode(substr($value, 1, -1), ENT_QUOTES, 'UTF-8');
				} else {
					$el->attrs[$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
				}
			}
		}

		$res = $this->texy->invokeAroundHandlers('htmlTag', $parser, [$el, $isStart, $isEmpty]);

		if ($res instanceof HtmlElement) {
			return $this->texy->protect($isStart ? $res->startTag() : $res->endTag(), $res->getContentType());
		}

		return $res;
	}


	/**
	 * Finish invocation.
	 * @return HtmlElement|string|false
	 */
	public function solveTag(Texy\HandlerInvocation $invocation, HtmlElement $el, $isStart, $forceEmpty = null)
	{
		$texy = $this->texy;

		// tag & attibutes
		$allowedTags = $texy->allowedTags; // speed-up
		if (!$allowedTags) {
			return false; // all tags are disabled
		}

		// convert case
		$name = $el->getName();
		$lower = strtolower($name);
		if (isset($texy->dtd[$lower]) || $name === strtoupper($name)) {
			// complete UPPER convert to lower
			$name = $lower;
			$el->setName($name);
		}

		if (is_array($allowedTags)) {
			if (!isset($allowedTags[$name])) {
				return false;
			}
			$allowedAttrs = $allowedTags[$name]; // allowed attrs

		} else {
			// allowedTags === Texy\Texy::ALL
			if ($forceEmpty) {
				$el->setName($name, true);
			}
			$allowedAttrs = $texy::ALL; // all attrs are allowed
		}

		// end tag? we are finished
		if (!$isStart) {
			return $el;
		}

		$elAttrs = &$el->attrs;

		// process attributes
		if (!$allowedAttrs) {
			$elAttrs = [];

		} elseif (is_array($allowedAttrs)) {

			// skip disabled
			$allowedAttrs = array_flip($allowedAttrs);
			foreach ($elAttrs as $key => $foo) {
				if (!isset($allowedAttrs[$key])) {
					unset($elAttrs[$key]);
				}
			}
		}

		// apply allowedClasses
		$tmp = $texy->_classes; // speed-up
		if (isset($elAttrs['class'])) {
			if (is_array($tmp)) {
				$elAttrs['class'] = explode(' ', $elAttrs['class']);
				foreach ($elAttrs['class'] as $key => $value) {
					if (!isset($tmp[$value])) {
						unset($elAttrs['class'][$key]); // id & class are case-sensitive
					}
				}

			} elseif ($tmp !== $texy::ALL) {
				$elAttrs['class'] = null;
			}
		}

		// apply allowedClasses for ID
		if (isset($elAttrs['id'])) {
			if (is_array($tmp)) {
				if (!isset($tmp['#' . $elAttrs['id']])) {
					$elAttrs['id'] = null;
				}
			} elseif ($tmp !== $texy::ALL) {
				$elAttrs['id'] = null;
			}
		}

		// apply allowedStyles
		if (isset($elAttrs['style'])) {
			$tmp = $texy->_styles; // speed-up
			if (is_array($tmp)) {
				$styles = explode(';', $elAttrs['style']);
				$elAttrs['style'] = null;
				foreach ($styles as $value) {
					$pair = explode(':', $value, 2);
					$prop = trim($pair[0]);
					if (isset($pair[1]) && isset($tmp[strtolower($prop)])) { // CSS is case-insensitive
						$elAttrs['style'][$prop] = $pair[1];
					}
				}
			} elseif ($tmp !== $texy::ALL) {
				$elAttrs['style'] = null;
			}
		}

		foreach (['src', 'href', 'name', 'id'] as $attr) {
			if (isset($elAttrs[$attr])) {
				$elAttrs[$attr] = is_string($elAttrs[$attr]) ? trim($elAttrs[$attr]) : '';
				if ($elAttrs[$attr] === '') {
					unset($elAttrs[$attr]);
				}
			}
		}

		if ($name === 'img') {
			if (!isset($elAttrs['src']) || !$texy->checkURL($elAttrs['src'], $texy::FILTER_IMAGE)) {
				return false;
			}
			$texy->summary['images'][] = $elAttrs['src'];

		} elseif ($name === 'a') {
			if (!isset($elAttrs['href']) && !isset($elAttrs['name']) && !isset($elAttrs['id'])) {
				return false;
			}
			if (isset($elAttrs['href'])) {
				if ($texy->linkModule->forceNoFollow && strpos($elAttrs['href'], '//') !== false) {
					if (isset($elAttrs['rel'])) {
						$elAttrs['rel'] = (array) $elAttrs['rel'];
					}
					$elAttrs['rel'][] = 'nofollow';
				}

				if (!$texy->checkURL($elAttrs['href'], $texy::FILTER_ANCHOR)) {
					return false;
				}

				$texy->summary['links'][] = $elAttrs['href'];
			}

		} elseif (preg_match('#^h[1-6]#i', $name)) {
			$texy->headingModule->TOC[] = [
				'el' => $el,
				'level' => (int) substr($name, 1),
				'type' => 'html',
			];
		}

		$el->validateAttrs($texy->dtd);

		return $el;
	}


	/**
	 * Finish invocation.
	 * @return string
	 */
	public function solveComment(Texy\HandlerInvocation $invocation, $content)
	{
		if (!$this->passComment) {
			return '';
		}

		// sanitize comment
		$content = Texy\Regexp::replace($content, '#-{2,}#', ' - ');
		$content = trim($content, '-');

		return $this->texy->protect('<!--' . $content . '-->', Texy\Texy::CONTENT_MARKUP);
	}
}
