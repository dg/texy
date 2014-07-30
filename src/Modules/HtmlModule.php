<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Html tags module.
 *
 * @author     David Grudl
 */
final class HtmlModule extends Texy\Module
{
	/** @var bool   pass HTML comments to output? */
	public $passComment = TRUE;


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('htmlComment', array($this, 'solveComment'));
		$texy->addHandler('htmlTag', array($this, 'solveTag'));

		$texy->registerLinePattern(
			array($this, 'patternTag'),
			'#<(/?)([a-z][a-z0-9_:-]{0,50})((?:\s++[a-z0-9:-]++|=\s*+"[^"'.Texy\Patterns::MARK.']*+"|=\s*+\'[^\''.Texy\Patterns::MARK.']*+\'|=[^\s>'.Texy\Patterns::MARK.']++)*)\s*+(/?)>#isu',
			'html/tag'
		);

		$texy->registerLinePattern(
			array($this, 'patternComment'),
			'#<!--([^'.Texy\Patterns::MARK.']*?)-->#is',
			'html/comment'
		);
	}


	/**
	 * Callback for: <!-- comment -->.
	 *
	 * @param  Texy\LineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function patternComment($parser, $matches)
	{
		list(, $mComment) = $matches;
		return $this->texy->invokeAroundHandlers('htmlComment', $parser, array($mComment));
	}


	/**
	 * Callback for: <tag attr="...">.
	 *
	 * @param  Texy\LineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function patternTag($parser, $matches)
	{
		list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
		// [1] => /
		// [2] => tag
		// [3] => attributes
		// [4] => /

		$tx = $this->texy;

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && substr($mAttr, -1) === '/') { // uvizlo v $mAttr?
			$mAttr = substr($mAttr, 0, -1);
			$isEmpty = TRUE;
		}

		// error - can't close empty element
		if ($isEmpty && !$isStart) {
			return FALSE;
		}

		// error - end element with atttrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return FALSE;
		}

		$el = Texy\HtmlElement::el($mTag);

		if ($isStart) {
			// parse attributes
			$matches2 = NULL;
			preg_match_all(
				'#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#isu',
				$mAttr,
				$matches2,
				PREG_SET_ORDER
			);

			foreach ($matches2 as $m) {
				$key = strtolower($m[1]);
				$value = $m[2];
				if ($value == NULL) {
					$el->attrs[$key] = TRUE;
				} elseif ($value{0} === '\'' || $value{0} === '"') {
					$el->attrs[$key] = Texy\Texy::unescapeHtml(substr($value, 1, -1));
				} else {
					$el->attrs[$key] = Texy\Texy::unescapeHtml($value);
				}
			}
		}

		$res = $tx->invokeAroundHandlers('htmlTag', $parser, array($el, $isStart, $isEmpty));

		if ($res instanceof Texy\HtmlElement) {
			return $tx->protect($isStart ? $res->startTag() : $res->endTag(), $res->getContentType());
		}

		return $res;
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\HtmlElement  element
	 * @param  bool      is start tag?
	 * @param  bool      is empty?
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function solveTag($invocation, Texy\HtmlElement $el, $isStart, $forceEmpty = NULL)
	{
		$tx = $this->texy;

		// tag & attibutes
		$allowedTags = $tx->allowedTags; // speed-up
		if (!$allowedTags) {
			return FALSE; // all tags are disabled
		}

		// convert case
		$name = $el->getName();
		$lower = strtolower($name);
		if (isset($tx->dtd[$lower]) || $name === strtoupper($name)) {
			// complete UPPER convert to lower
			$name = $lower;
			$el->setName($name);
		}

		if (is_array($allowedTags)) {
			if (!isset($allowedTags[$name])) {
				return FALSE;
			}
			$allowedAttrs = $allowedTags[$name]; // allowed attrs

		} else {
			// allowedTags === Texy\Texy::ALL
			if ($forceEmpty) {
				$el->setName($name, TRUE);
			}
			$allowedAttrs = Texy\Texy::ALL; // all attrs are allowed
		}

		// end tag? we are finished
		if (!$isStart) {
			return $el;
		}

		$elAttrs = & $el->attrs;

		// process attributes
		if (!$allowedAttrs) {
			$elAttrs = array();

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
		$tmp = $tx->_classes; // speed-up
		if (isset($elAttrs['class'])) {
			if (is_array($tmp)) {
				$elAttrs['class'] = explode(' ', $elAttrs['class']);
				foreach ($elAttrs['class'] as $key => $value) {
					if (!isset($tmp[$value])) {
						unset($elAttrs['class'][$key]); // id & class are case-sensitive
					}
				}

			} elseif ($tmp !== Texy\Texy::ALL) {
				$elAttrs['class'] = NULL;
			}
		}

		// apply allowedClasses for ID
		if (isset($elAttrs['id'])) {
			if (is_array($tmp)) {
				if (!isset($tmp['#' . $elAttrs['id']])) {
					$elAttrs['id'] = NULL;
				}
			} elseif ($tmp !== Texy\Texy::ALL) {
				$elAttrs['id'] = NULL;
			}
		}

		// apply allowedStyles
		if (isset($elAttrs['style'])) {
			$tmp = $tx->_styles; // speed-up
			if (is_array($tmp)) {
				$styles = explode(';', $elAttrs['style']);
				$elAttrs['style'] = NULL;
				foreach ($styles as $value) {
					$pair = explode(':', $value, 2);
					$prop = trim($pair[0]);
					if (isset($pair[1]) && isset($tmp[strtolower($prop)])) { // CSS is case-insensitive
						$elAttrs['style'][$prop] = $pair[1];
					}
				}
			} elseif ($tmp !== Texy\Texy::ALL) {
				$elAttrs['style'] = NULL;
			}
		}

		if ($name === 'img') {
			if (!isset($elAttrs['src']) || !$tx->checkURL($elAttrs['src'], Texy\Texy::FILTER_IMAGE)) {
				return FALSE;
			}

			$tx->summary['images'][] = $elAttrs['src'];

		} elseif ($name === 'a') {
			if (!isset($elAttrs['href']) && !isset($elAttrs['name']) && !isset($elAttrs['id'])) {
				return FALSE;
			}
			if (isset($elAttrs['href'])) {
				if ($tx->linkModule->forceNoFollow && strpos($elAttrs['href'], '//') !== FALSE) {
					if (isset($elAttrs['rel'])) {
						$elAttrs['rel'] = (array) $elAttrs['rel'];
					}
					$elAttrs['rel'][] = 'nofollow';
				}

				if (!$tx->checkURL($elAttrs['href'], Texy\Texy::FILTER_ANCHOR)) {
					return FALSE;
				}

				$tx->summary['links'][] = $elAttrs['href'];
			}

		} elseif (preg_match('#^h[1-6]#i', $name)) {
			$tx->headingModule->TOC[] = array(
				'el' => $el,
				'level' => (int) substr($name, 1),
				'type' => 'html',
			);
		}

		$el->validateAttrs($tx->dtd);

		return $el;
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @return string
	 */
	public function solveComment($invocation, $content)
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
