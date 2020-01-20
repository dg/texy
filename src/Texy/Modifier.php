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


	public function __construct(string $s = null)
	{
		$this->setProperties($s);
	}


	public function setProperties(?string $s): void
	{
		$p = 0;
		$len = $s ? strlen($s) : 0;

		while ($p < $len) {
			$ch = $s[$p];

			if ($ch === '(') { // title
				preg_match('#(?:\\\\\)|[^)\n])++\)#', $s, $m, 0, $p);
				$this->title = Helpers::unescapeHtml(str_replace('\)', ')', trim(substr($m[0], 1, -1))));
				$p += strlen($m[0]);

			} elseif ($ch === '{') { // style & attributes
				$a = strpos($s, '}', $p) + 1;
				$this->parseStyle(substr($s, $p + 1, $a - $p - 2));
				$p = $a;

			} elseif ($ch === '[') { // classes & ID
				$a = strpos($s, ']', $p) + 1;
				$this->parseClasses(str_replace('#', ' #', substr($s, $p + 1, $a - $p - 2)));
				$p = $a;

			} elseif ($val = ['^' => 'top', '-' => 'middle', '_' => 'bottom'][$ch] ?? null) { // alignment
				$this->vAlign = $val;
				$p++;

			} elseif (substr($s, $p, 2) === '<>') {
				$this->hAlign = 'center';
				$p += 2;

			} elseif ($val = ['=' => 'justify', '>' => 'right', '<' => 'left'][$ch] ?? null) {
				$this->hAlign = $val;
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
		$this->decorateAttrs($texy, $el->attrs, $el->getName());
		$el->validateAttrs($texy->getDTD());
		$this->decorateClasses($texy, $el->attrs);
		$this->decorateStyles($texy, $el->attrs);
		$this->decorateAligns($texy, $el->attrs);
		return $el;
	}


	private function decorateAttrs(Texy $texy, array &$attrs, string $name): void
	{
		if (!$this->attrs) {
		} elseif ($texy->allowedTags === $texy::ALL) {
			$attrs = $this->attrs;

		} elseif (is_array($texy->allowedTags)) {
			$attrs = $texy->allowedTags[$name] ?? null;

			if ($attrs === $texy::ALL) {
				$attrs = $this->attrs;

			} elseif (is_array($attrs) && count($attrs)) {
				$attrs = array_flip($attrs);
				foreach ($this->attrs as $key => $value) {
					if (isset($attrs[$key])) {
						$attrs[$key] = $value;
					}
				}
			}
		}

		if ($this->title !== null) {
			$attrs['title'] = $texy->typographyModule->postLine($this->title);
		}
	}


	private function decorateClasses(Texy $texy, array &$attrs): void
	{
		if ($this->classes || $this->id !== null) {
			[$allowedClasses] = $texy->getAllowedProps();
			settype($attrs['class'], 'array');
			if ($allowedClasses === $texy::ALL) {
				foreach ($this->classes as $value => $foo) {
					$attrs['class'][] = $value;
				}
				$attrs['id'] = $this->id;
			} elseif (is_array($allowedClasses)) {
				foreach ($this->classes as $value => $foo) {
					if (isset($allowedClasses[$value])) {
						$attrs['class'][] = $value;
					}
				}

				if (isset($allowedClasses['#' . $this->id])) {
					$attrs['id'] = $this->id;
				}
			}
		}
	}


	private function decorateStyles(Texy $texy, array &$attrs): void
	{
		if ($this->styles) {
			[, $allowedStyles] = $texy->getAllowedProps();
			settype($attrs['style'], 'array');
			if ($allowedStyles === $texy::ALL) {
				foreach ($this->styles as $prop => $value) {
					$attrs['style'][$prop] = $value;
				}
			} elseif (is_array($allowedStyles)) {
				foreach ($this->styles as $prop => $value) {
					if (isset($allowedStyles[$prop])) {
						$attrs['style'][$prop] = $value;
					}
				}
			}
		}
	}


	private function decorateAligns(Texy $texy, array &$attrs): void
	{
		if ($this->hAlign) {
			$class = $texy->alignClasses[$this->hAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['text-align'] = $this->hAlign;
			}
		}

		if ($this->vAlign) {
			$class = $texy->alignClasses[$this->vAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['vertical-align'] = $this->vAlign;
			}
		}
	}


	private function parseStyle(string $s): void
	{
		foreach (explode(';', $s) as $value) {
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
	}


	private function parseClasses(string $s): void
	{
		foreach (explode(' ', $s) as $value) {
			if ($value === '') {
				continue;
			} elseif ($value[0] === '#') {
				$this->id = substr($value, 1);
			} else {
				$this->classes[$value] = true;
			}
		}
	}
}
