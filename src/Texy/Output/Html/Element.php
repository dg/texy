<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Helpers;
use function implode, is_array, is_object, is_string, str_replace;


/**
 * HTML helper.
 *
 * usage:
 * $el = new Element('a', ['href' => $link]);
 * $el->children[] = 'Texy';
 * echo $el->startTag(), $el->endTag();
 */
class Element
{
	public ?string $name;

	/** @var array<string, string|int|bool|array<string|int|bool>|null>  element's attributes */
	public array $attrs = [];

	/** @var list<Element|string> */
	public array $children = [];

	private bool $isVoid;


	/** @param  array<string, mixed>|string|null  $attrs  element's attributes (or textual content) */
	public function __construct(?string $name = null, array|string|null $attrs = null)
	{
		$this->name = $name;
		$this->isVoid = isset(Schema::voidElements()[$name ?? '']);
		if (is_array($attrs)) {
			$this->attrs = $attrs;
		} elseif ($attrs !== null) {
			$this->children = [$attrs];
		}
	}


	/**
	 * Is element empty?
	 */
	public function isVoid(): bool
	{
		return $this->isVoid;
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
		if ($this->name && !$this->isVoid) {
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
