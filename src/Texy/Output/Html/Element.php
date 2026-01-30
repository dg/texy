<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Html;

use Texy\Helpers;
use function implode, is_array, is_object, is_string, str_replace;


/**
 * HTML helper.
 *
 * usage:
 * $el = new HtmlElement('a', ['href' => $link]);
 * $el->children[] = 'Texy';
 * echo $el->startTag(), $el->endTag();
 */
class Element
{
	/** @var array<string, 1>  void elements */
	public static array $emptyElements = [
		'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1,
		'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1,
	];

	/** @var array<string, int>  phrasing elements; replaced elements + br have value 1 */
	public static array $inlineElements = [
		'a' => 0, 'abbr' => 0, 'area' => 0, 'audio' => 0, 'b' => 0, 'bdi' => 0, 'bdo' => 0, 'br' => 1, 'button' => 1, 'canvas' => 1,
		'cite' => 0, 'code' => 0, 'data' => 0, 'datalist' => 0, 'del' => 0, 'dfn' => 0, 'em' => 0, 'embed' => 1, 'i' => 0, 'iframe' => 1,
		'img' => 1, 'input' => 1, 'ins' => 0, 'kbd' => 0, 'label' => 0, 'link' => 0, 'map' => 0, 'mark' => 0, 'math' => 1, 'meta' => 0,
		'meter' => 1, 'noscript' => 1, 'object' => 1, 'output' => 1, 'picture' => 1, 'progress' => 1, 'q' => 0, 'ruby' => 0, 's' => 0,
		'samp' => 0, 'script' => 1, 'select' => 1, 'slot' => 0, 'small' => 0, 'span' => 0, 'strong' => 0, 'sub' => 0, 'sup' => 0,
		'svg' => 1, 'template' => 0, 'textarea' => 1, 'time' => 0, 'u' => 0, 'var' => 0, 'video' => 1, 'wbr' => 0,
	];

	public ?string $name;

	/** @var array<string, string|int|bool|array<string|int|bool>|null>  element's attributes */
	public array $attrs = [];

	/** @var array<Element|string> */
	public array $children = [];

	private bool $isEmpty;


	/**
	 * @param  array<string, mixed>|string|null  $attrs  element's attributes (or textual content)
	 */
	public function __construct(?string $name = null, array|string|null $attrs = null)
	{
		$this->name = $name;
		$this->isEmpty = isset(self::$emptyElements[$name ?? '']);
		if (is_array($attrs)) {
			$this->attrs = $attrs;
		} elseif ($attrs !== null) {
			$this->children = [$attrs];
		}
	}


	/**
	 * Is element empty?
	 */
	public function isEmpty(): bool
	{
		return $this->isEmpty;
	}


	/**
	 * Sets element's textual content.
	 */
	public function setText(string $text): static
	{
		$this->children = [$text];
		return $this;
	}


	/**
	 * Adds new element's child.
	 */
	public function add(self|string $child): static
	{
		$this->children[] = $child;
		return $this;
	}


	/**
	 * Creates and adds a new HtmlElement child.
	 * @param  array<string, mixed>|string|null  $attrs
	 */
	public function create(string $name, array|string|null $attrs = null): self
	{
		$this->children[] = $child = new self($name, $attrs);
		return $child;
	}


	/**
	 * Returns element's start tag.
	 */
	public function startTag(): string
	{
		if (!$this->name) {
			return '';
		}

		return '<' . $this->name . self::formatAttrs($this->attrs) . '>';
	}


	/**
	 * Formats attributes array to HTML string.
	 * @param  array<string, string|int|bool|array<string|int|bool>|null>  $attrs
	 */
	public static function formatAttrs(array $attrs): string
	{
		$s = '';
		foreach ($attrs as $key => $value) {
			if ($value === null || $value === false) {
				continue; // skip nulls and false boolean attributes

			} elseif ($value === true) {
				$s .= ' ' . $key; // true boolean attribute
				continue;

			} elseif (is_array($value)) {
				$tmp = null;
				foreach ($value as $k => $v) {
					if ($v == null) { // skip nulls & empty string; composite 'style' vs. 'others'
						continue;
					} elseif (is_string($k)) {
						$tmp[] = $k . ':' . $v;
					} else {
						$tmp[] = $v;
					}
				}

				if (!$tmp) {
					continue;
				}

				$value = implode($key === 'style' ? ';' : ' ', $tmp);

			} else {
				$value = (string) $value;
			}

			// add new attribute
			$value = str_replace(['&', '"', '<', '>', '@'], ['&amp;', '&quot;', '&lt;', '&gt;', '&#64;'], $value);
			$s .= ' ' . $key . '="' . Helpers::freezeSpaces($value) . '"';
		}

		return $s;
	}


	/**
	 * Returns element's end tag.
	 */
	public function endTag(): string
	{
		if ($this->name && !$this->isEmpty) {
			return '</' . $this->name . '>';
		}

		return '';
	}


	/**
	 * Clones all children too.
	 */
	public function __clone()
	{
		foreach ($this->children as $key => $value) {
			if (is_object($value)) {
				$this->children[$key] = clone $value;
			}
		}
	}
}
