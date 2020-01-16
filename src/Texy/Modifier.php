<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Modifier processor.
 *
 * Modifiers are texts like .(title)[class1 class2 #id]{color: red}>^
 *   .         starts with dot
 *   (...)     title or alt modifier
 *   [...]     classes or ID modifier
 *   {...}     inner style modifier
 *   < > <> =  horizontal align modifier
 *   ^ - _     vertical align modifier
 */
final class Modifier
{
	use Strict;

	/** @var string|null */
	public $id;

	/** @var array<string, bool> of classes (as keys) */
	public $classes = [];

	/** @var array<string, string> of CSS styles */
	public $styles = [];

	/** @var array<string, string|string[]> of HTML element attributes */
	public $attrs = [];

	/** @var string|null */
	public $hAlign;

	/** @var string|null */
	public $vAlign;

	/** @var string|null */
	public $title;

	/** @var string|null */
	public $cite;

	/** @var array<string, int>  list of properties which are regarded as HTML element attributes */
	public static $elAttrs = [
		'abbr' => 1, 'accesskey' => 1, 'alt' => 1, 'cite' => 1, 'colspan' => 1, 'contenteditable' => 1, 'crossorigin' => 1,
		'datetime' => 1, 'decoding' => 1, 'download' => 1, 'draggable' => 1, 'for' => 1, 'headers' => 1, 'hidden' => 1,
		'href' => 1, 'hreflang' => 1, 'id' => 1, 'itemid' => 1, 'itemprop' => 1, 'itemref' => 1, 'itemscope' => 1, 'itemtype' => 1,
		'lang' => 1, 'name' => 1, 'ping' => 1, 'referrerpolicy' => 1, 'rel' => 1, 'reversed' => 1, 'rowspan' => 1, 'scope' => 1,
		'slot' => 1, 'src' => 1, 'srcset' => 1, 'start' => 1, 'target' => 1, 'title' => 1, 'translate' => 1, 'type' => 1, 'value' => 1,
	];


	public function __construct(string $mod = null)
	{
		$this->setProperties($mod);
	}


	public function setProperties(?string $mod): void
	{
		if (!$mod) {
			return;
		}

		$p = 0;
		$len = strlen($mod);

		while ($p < $len) {
			$ch = $mod[$p];

			if ($ch === '(') { // title
				preg_match('#(?:\\\\\)|[^)\n])++\)#', $mod, $m, 0, $p);
				$this->title = Helpers::unescapeHtml(str_replace('\)', ')', trim(substr($m[0], 1, -1))));
				$p += strlen($m[0]);

			} elseif ($ch === '{') { // style & attributes
				$a = strpos($mod, '}', $p) + 1;
				foreach (explode(';', substr($mod, $p + 1, $a - $p - 2)) as $value) {
					$pair = explode(':', $value, 2);
					$prop = strtolower(trim($pair[0]));
					if ($prop === '' || !isset($pair[1])) {
						continue;
					}
					$value = trim($pair[1]);

					if (isset(self::$elAttrs[$prop]) || substr($prop, 0, 5) === 'data-') { // attribute
						$this->attrs[$prop] = $value;
					} elseif ($value !== '') { // style
						$this->styles[$prop] = $value;
					}
				}
				$p = $a;

			} elseif ($ch === '[') { // classes & ID
				$a = strpos($mod, ']', $p) + 1;
				$s = str_replace('#', ' #', substr($mod, $p + 1, $a - $p - 2));
				foreach (explode(' ', $s) as $value) {
					if ($value === '') {
						continue;
					} elseif ($value[0] === '#') {
						$this->id = substr($value, 1);
					} else {
						$this->classes[$value] = true;
					}
				}
				$p = $a;

			} elseif ($ch === '^') { // alignment
				$this->vAlign = 'top';
				$p++;
			} elseif ($ch === '-') {
				$this->vAlign = 'middle';
				$p++;
			} elseif ($ch === '_') {
				$this->vAlign = 'bottom';
				$p++;
			} elseif ($ch === '=') {
				$this->hAlign = 'justify';
				$p++;
			} elseif ($ch === '>') {
				$this->hAlign = 'right';
				$p++;
			} elseif (substr($mod, $p, 2) === '<>') {
				$this->hAlign = 'center';
				$p += 2;
			} elseif ($ch === '<') {
				$this->hAlign = 'left';
				$p++;
			} else {
				break;
			}
		}
	}


	/**
	 * Decorates HtmlElement element.
	 */
	public function decorate(Texy $texy, HtmlElement $el): HtmlElement
	{
		$elAttrs = &$el->attrs;

		// tag & attibutes
		$tags = $texy->allowedTags; // speed-up
		if (!$this->attrs) {
		} elseif ($tags === $texy::ALL) {
			$elAttrs = $this->attrs;
			$el->validateAttrs($texy->getDTD());

		} elseif (is_array($tags) && isset($tags[$el->getName()])) {
			$attrs = $tags[$el->getName()];

			if ($attrs === $texy::ALL) {
				$elAttrs = $this->attrs;

			} elseif (is_array($attrs) && count($attrs)) {
				$attrs = array_flip($attrs);
				foreach ($this->attrs as $key => $value) {
					if (isset($attrs[$key])) {
						$el->attrs[$key] = $value;
					}
				}
			}
			$el->validateAttrs($texy->getDTD());
		}

		// title
		if ($this->title !== null) {
			$elAttrs['title'] = $texy->typographyModule->postLine($this->title);
		}

		// classes & ID
		[$classes, $styles] = $texy->getAllowedProps();
		if ($this->classes || $this->id !== null) {
			settype($elAttrs['class'], 'array');
			if ($classes === $texy::ALL) {
				foreach ($this->classes as $value => $foo) {
					$elAttrs['class'][] = $value;
				}
				$elAttrs['id'] = $this->id;
			} elseif (is_array($classes)) {
				foreach ($this->classes as $value => $foo) {
					if (isset($classes[$value])) {
						$elAttrs['class'][] = $value;
					}
				}

				if (isset($classes['#' . $this->id])) {
					$elAttrs['id'] = $this->id;
				}
			}
		}

		// styles
		if ($this->styles) {
			settype($elAttrs['style'], 'array');
			if ($styles === $texy::ALL) {
				foreach ($this->styles as $prop => $value) {
					$elAttrs['style'][$prop] = $value;
				}
			} elseif (is_array($styles)) {
				foreach ($this->styles as $prop => $value) {
					if (isset($styles[$prop])) {
						$elAttrs['style'][$prop] = $value;
					}
				}
			}
		}

		// horizontal align
		if ($this->hAlign) {
			if (empty($texy->alignClasses[$this->hAlign])) {
				$elAttrs['style']['text-align'] = $this->hAlign;
			} else {
				$elAttrs['class'][] = $texy->alignClasses[$this->hAlign];
			}
		}

		// vertical align
		if ($this->vAlign) {
			if (empty($texy->alignClasses[$this->vAlign])) {
				$elAttrs['style']['vertical-align'] = $this->vAlign;
			} else {
				$elAttrs['class'][] = $texy->alignClasses[$this->vAlign];
			}
		}

		return $el;
	}
}
