<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_flip, explode, is_array, settype, str_replace, str_starts_with, strlen, strpos, strtolower, substr, trim;


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
	public ?string $id = null;

	/** @var array<string, true> of classes (as keys) */
	public array $classes = [];

	/** @var array<string, string> of CSS styles */
	public array $styles = [];

	/** @var array<string, string|string[]> of HTML element attributes */
	public array $attrs = [];
	public ?string $hAlign = null;
	public ?string $vAlign = null;
	public ?string $title = null;

	/** @var array<string, 1>  list of properties which are regarded as HTML element attributes */
	public static array $elAttrs = [
		'abbr' => 1, 'accesskey' => 1, 'alt' => 1, 'cite' => 1, 'colspan' => 1, 'contenteditable' => 1, 'crossorigin' => 1,
		'datetime' => 1, 'decoding' => 1, 'download' => 1, 'draggable' => 1, 'for' => 1, 'headers' => 1, 'hidden' => 1,
		'href' => 1, 'hreflang' => 1, 'id' => 1, 'itemid' => 1, 'itemprop' => 1, 'itemref' => 1, 'itemscope' => 1, 'itemtype' => 1,
		'lang' => 1, 'name' => 1, 'ping' => 1, 'referrerpolicy' => 1, 'rel' => 1, 'reversed' => 1, 'rowspan' => 1, 'scope' => 1,
		'slot' => 1, 'src' => 1, 'srcset' => 1, 'start' => 1, 'target' => 1, 'title' => 1, 'translate' => 1, 'type' => 1, 'value' => 1,
	];


	/**
	 * Parses modifier string and returns new instance.
	 */
	public static function parse(?string $s): self
	{
		$modifier = new self;
		if ($s !== null) {
			$modifier->setProperties($s);
		}
		return $modifier;
	}


	/** @deprecated  use Modifier::parse() */
	public function setProperties(?string $s): void
	{
		$p = 0;
		$len = $s ? strlen($s) : 0;

		while ($p < $len) {
			$ch = $s[$p];

			if ($ch === '(') { // title
				$m = Regexp::match($s, '~(?: \\\\\) | [^)\n] )++\)~', offset: $p);
				if (isset($m[0])) {
					$this->title = Helpers::unescapeHtml(str_replace('\)', ')', trim(substr($m[0], 1, -1))));
					$p += strlen($m[0]);
				}

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
		$this->decorateAttrs($texy, $el->attrs, $el->getName() ?? '');
		$el->validateAttrs($texy->getDTD());
		$this->decorateClasses($texy, $el->attrs);
		$this->decorateStyles($texy, $el->attrs);
		$this->decorateAligns($texy, $el->attrs);
		return $el;
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateAttrs(Texy $texy, array &$attrs, string $name): void
	{
		if (!$this->attrs) {
		} elseif ($texy->allowedTags === $texy::ALL) {
			$attrs = $this->attrs;

		} elseif (is_array($texy->allowedTags)) {
			$tmp = $texy->allowedTags[$name] ?? [];

			if ($tmp === $texy::ALL) {
				$attrs = $this->attrs;

			} elseif (is_array($tmp)) {
				$attrs = array_flip($tmp);
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


	/** @param  array<string, mixed>  $attrs */
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


	/** @param  array<string, mixed>  $attrs */
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


	/** @param  array<string, mixed>  $attrs */
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

			if (
				isset(self::$elAttrs[$prop])
				|| str_starts_with($prop, 'data-')
				|| str_starts_with($prop, 'aria-')
			) { // attribute
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
