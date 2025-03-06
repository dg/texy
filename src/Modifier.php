<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function is_array, strlen;


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
	public static function parse(?string $s): ?self
	{
		if ($s === null) {
			return null;
		}
		$modifier = new self;
		$modifier->setProperties($s);
		return $modifier;
	}


	/** @internal  adds the parts of a modifier string to this one */
	public function setProperties(?string $s): void
	{
		$p = 0;
		$len = $s ? strlen($s) : 0;

		while ($p < $len) {
			$ch = $s[$p];

			if ($ch === '(') { // title
				$m = Regexp::match($s, '~(?: \\\\\) | [^)\n] )++\)~x', offset: $p);
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
	 * Decorates the element with the attributes, classes and styles of the modifier, as far as
	 * $allowedTags, $allowedClasses and $allowedStyles of Texy let them through.
	 */
	public function decorate(Texy $texy, Output\Html\Element $el): Output\Html\Element
	{
		if ($this->attrs) {
			$el->attrs = $this->filterAttrs($texy->allowedTags, $el->name ?? '');
		}

		if ($this->title !== null) {
			$el->attrs['title'] = empty($texy->allowed[Syntax::Typography])
				? $this->title
				: $texy->typographyModule->postLine($this->title);
		}

		$this->decorateClasses($texy->allowedClasses, $el->attrs);
		$this->decorateStyles($texy->allowedStyles, $el->attrs);

		if ($this->hAlign) {
			self::applyClassOrStyle($el->attrs, $texy->alignClasses[$this->hAlign] ?? null, 'text-align', $this->hAlign);
		}

		if ($this->vAlign) {
			self::applyClassOrStyle($el->attrs, $texy->alignClasses[$this->vAlign] ?? null, 'vertical-align', $this->vAlign);
		}

		return $el;
	}


	/**
	 * The attributes which $allowedTags allows for the tag.
	 * @param  bool|array<string, bool|array<int, string>>  $allowedTags
	 * @return array<string, string|string[]>
	 */
	private function filterAttrs(bool|array $allowedTags, string $tagName): array
	{
		$allowed = is_array($allowedTags) ? $allowedTags[$tagName] ?? [] : $allowedTags;
		return match (true) {
			$allowed === Texy::ALL => $this->attrs,
			is_array($allowed) => array_intersect_key($this->attrs, array_flip($allowed)),
			default => [],
		};
	}


	/**
	 * @param  bool|array<int, string>  $allowedClasses
	 * @param  array<string, mixed>  $attrs
	 */
	private function decorateClasses(bool|array $allowedClasses, array &$attrs): void
	{
		if ($this->classes || $this->id !== null) {
			$allowedClasses = is_array($allowedClasses) ? array_flip($allowedClasses) : $allowedClasses;
			settype($attrs['class'], 'array');
			if ($allowedClasses === true) {
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


	/**
	 * @param  bool|array<int, string>  $allowedStyles
	 * @param  array<string, mixed>  $attrs
	 */
	private function decorateStyles(bool|array $allowedStyles, array &$attrs): void
	{
		if ($this->styles) {
			$allowedStyles = is_array($allowedStyles) ? array_flip($allowedStyles) : $allowedStyles;
			settype($attrs['style'], 'array');
			if ($allowedStyles === true) {
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


	/**
	 * Applies an alignment or a float as its class, or as the inline style when there is no class for it.
	 * @param  array<string, mixed>  $attrs
	 * @internal
	 */
	public static function applyClassOrStyle(array &$attrs, ?string $class, string $property, string $value): void
	{
		if ($class) {
			$attrs['class'] = (array) ($attrs['class'] ?? []);
			$attrs['class'][] = $class;
		} else {
			$attrs['style'] = (array) ($attrs['style'] ?? []);
			$attrs['style'][$property] = $value;
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
