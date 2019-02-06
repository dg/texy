<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * HTML helper.
 *
 * usage:
 * $anchor = (new HtmlElement('a'))->href($link)->setText('Texy');
 * $el->class = 'myclass';
 *
 * echo $el->startTag(), $el->endTag();
 */
class HtmlElement implements \ArrayAccess, /* Countable, */ \IteratorAggregate
{
	use Strict;

	/** @var array  element's attributes */
	public $attrs = [];

	/** @var bool  use XHTML syntax? */
	public static $xhtml = true;

	/** @var array  empty elements */
	public static $emptyElements = ['img' => 1, 'hr' => 1, 'br' => 1, 'input' => 1, 'meta' => 1, 'area' => 1,
		'base' => 1, 'col' => 1, 'link' => 1, 'param' => 1, 'basefont' => 1, 'frame' => 1, 'isindex' => 1, 'wbr' => 1, 'embed' => 1, ];

	/** @var array  %inline; elements; replaced elements + br have value '1' */
	public static $inlineElements = ['ins' => 0, 'del' => 0, 'tt' => 0, 'i' => 0, 'b' => 0, 'big' => 0, 'small' => 0, 'em' => 0,
		'strong' => 0, 'dfn' => 0, 'code' => 0, 'samp' => 0, 'kbd' => 0, 'var' => 0, 'cite' => 0, 'abbr' => 0, 'acronym' => 0,
		'sub' => 0, 'sup' => 0, 'q' => 0, 'span' => 0, 'bdo' => 0, 'a' => 0, 'object' => 1, 'img' => 1, 'br' => 1, 'script' => 1,
		'map' => 0, 'input' => 1, 'select' => 1, 'textarea' => 1, 'label' => 0, 'button' => 1,
		'u' => 0, 's' => 0, 'strike' => 0, 'font' => 0, 'applet' => 1, 'basefont' => 0, // transitional
		'embed' => 1, 'wbr' => 0, 'nobr' => 0, 'canvas' => 1, // proprietary
	];

	/** @var array  elements with optional end tag in HTML */
	public static $optionalEnds = ['body' => 1, 'head' => 1, 'html' => 1, 'colgroup' => 1, 'dd' => 1,
		'dt' => 1, 'li' => 1, 'option' => 1, 'p' => 1, 'tbody' => 1, 'td' => 1, 'tfoot' => 1, 'th' => 1, 'thead' => 1, 'tr' => 1, ];

	/** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
	public static $prohibits = [
		'a' => ['a', 'button'],
		'img' => ['pre'],
		'object' => ['pre'],
		'big' => ['pre'],
		'small' => ['pre'],
		'sub' => ['pre'],
		'sup' => ['pre'],
		'input' => ['button'],
		'select' => ['button'],
		'textarea' => ['button'],
		'label' => ['button', 'label'],
		'button' => ['button'],
		'form' => ['button', 'form'],
		'fieldset' => ['button'],
		'iframe' => ['button'],
		'isindex' => ['button'],
	];

	/** @var array  of HtmlElement | string nodes */
	protected $children = [];

	/** @var string|null  element's name */
	private $name;

	/** @var bool  is element empty? */
	private $isEmpty;


	/**
	 * @param  array|string  $attrs  element's attributes (or textual content)
	 */
	public function __construct(string $name = null, $attrs = null)
	{
		$this->setName($name);
		if (is_array($attrs)) {
			$this->attrs = $attrs;
		} elseif ($attrs !== null) {
			$this->setText($attrs);
		}
	}


	public static function el(string $name = null, $attrs = null): self
	{
		return new self($name, $attrs);
	}


	/**
	 * Changes element's name.
	 * @throws InvalidArgumentException
	 */
	final public function setName(?string $name, bool $empty = null): self
	{
		if ($name !== null && !is_string($name)) {
			throw new \InvalidArgumentException('Name must be string or null.');
		}

		$this->name = $name;
		$this->isEmpty = $empty === null ? isset(self::$emptyElements[$name]) : (bool) $empty;
		return $this;
	}


	/**
	 * Returns element's name.
	 */
	final public function getName(): ?string
	{
		return $this->name;
	}


	/**
	 * Is element empty?
	 */
	final public function isEmpty(): bool
	{
		return $this->isEmpty;
	}


	/**
	 * Overloaded setter for element's attribute.
	 */
	final public function __set(string $name, $value): void
	{
		$this->attrs[$name] = $value;
	}


	/**
	 * Overloaded getter for element's attribute.
	 */
	final public function &__get(string $name)
	{
		return $this->attrs[$name];
	}


	/**
	 * Special setter for element's attribute.
	 */
	final public function href(string $path, array $query = null): self
	{
		if ($query) {
			$query = http_build_query($query, null, '&');
			if ($query !== '') {
				$path .= '?' . $query;
			}
		}
		$this->attrs['href'] = $path;
		return $this;
	}


	/**
	 * Sets element's textual content.
	 */
	final public function setText(string $text): self
	{
		if (is_scalar($text)) {
			$this->removeChildren();
			$this->children = [$text];
		} elseif ($text !== null) {
			throw new \InvalidArgumentException('Content must be scalar.');
		}
		return $this;
	}


	/**
	 * Gets element's textual content.
	 */
	final public function getText(): ?string
	{
		$s = '';
		foreach ($this->children as $child) {
			if (is_object($child)) {
				return null;
			}
			$s .= $child;
		}
		return $s;
	}


	/**
	 * Adds new element's child.
	 * @param  HtmlElement|string  $child node
	 */
	final public function add($child): self
	{
		return $this->insert(null, $child);
	}


	/**
	 * Creates and adds a new HtmlElement child.
	 * @param  array|string  $attrs element's attributes (or textual content)
	 */
	final public function create(string $name, $attrs = null): self
	{
		$this->insert(null, $child = new self($name, $attrs));
		return $child;
	}


	/**
	 * Inserts child node.
	 * @param  HtmlElement|string  $child node
	 * @throws Exception
	 */
	public function insert(?int $index, $child, bool $replace = false): self
	{
		if ($child instanceof self || is_string($child)) {
			if ($index === null) { // append
				$this->children[] = $child;

			} else { // insert or replace
				array_splice($this->children, (int) $index, $replace ? 1 : 0, [$child]);
			}

		} else {
			throw new \InvalidArgumentException('Child node must be scalar or HtmlElement object.');
		}

		return $this;
	}


	/**
	 * Inserts (replaces) child node (ArrayAccess implementation).
	 * @param  int  $index
	 * @param  HtmlElement  $child
	 */
	final public function offsetSet($index, $child): void
	{
		$this->insert($index, $child, true);
	}


	/**
	 * Returns child node (ArrayAccess implementation).
	 * @param  int  $index
	 * @return mixed
	 */
	final public function offsetGet($index)
	{
		return $this->children[$index];
	}


	/**
	 * Exists child node? (ArrayAccess implementation).
	 * @param  int  $index
	 */
	final public function offsetExists($index): bool
	{
		return isset($this->children[$index]);
	}


	/**
	 * Removes child node (ArrayAccess implementation).
	 * @param  int  $index
	 */
	public function offsetUnset($index): void
	{
		if (isset($this->children[$index])) {
			array_splice($this->children, (int) $index, 1);
		}
	}


	/**
	 * Required by the Countable interface.
	 */
	final public function count(): int
	{
		return count($this->children);
	}


	/**
	 * Removed all children.
	 */
	public function removeChildren(): void
	{
		$this->children = [];
	}


	/**
	 * Required by the IteratorAggregate interface.
	 */
	final public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->children);
	}


	/**
	 * Returns all of children.
	 */
	final public function getChildren(): array
	{
		return $this->children;
	}


	/**
	 * Renders element's start tag, content and end tag to internal string representation.
	 */
	final public function toString(Texy $texy): string
	{
		$ct = $this->getContentType();
		$s = $texy->protect($this->startTag(), $ct);

		// empty elements are finished now
		if ($this->isEmpty) {
			return $s;
		}

		// add content
		foreach ($this->children as $child) {
			if (is_object($child)) {
				$s .= $child->toString($texy);
			} else {
				$s .= $child;
			}
		}

		// add end tag
		return $s . $texy->protect($this->endTag(), $ct);
	}


	/**
	 * Renders to final HTML.
	 */
	final public function toHtml(Texy $texy): string
	{
		return $texy->stringToHtml($this->toString($texy));
	}


	/**
	 * Renders to final text.
	 */
	final public function toText(Texy $texy): string
	{
		return $texy->stringToText($this->toString($texy));
	}


	/**
	 * Returns element's start tag.
	 */
	public function startTag(): string
	{
		if (!$this->name) {
			return '';
		}

		$s = '<' . $this->name;

		if (is_array($this->attrs)) {
			foreach ($this->attrs as $key => $value) {
				// skip nulls and false boolean attributes
				if ($value === null || $value === false) {
					continue;
				}

				// true boolean attribute
				if ($value === true) {
					// in XHTML must use unminimized form
					if (self::$xhtml) {
						$s .= ' ' . $key . '="' . $key . '"';
					} else {
						$s .= ' ' . $key;
					}
					continue;

				} elseif (is_array($value)) {

					// prepare into temporary array
					$tmp = null;
					foreach ($value as $k => $v) {
						// skip nulls & empty string; composite 'style' vs. 'others'
						if ($v == null) {
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
		}

		// finish start tag
		if (self::$xhtml && $this->isEmpty) {
			return $s . ' />';
		}
		return $s . '>';
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


	final public function getContentType(): string
	{
		if (!isset(self::$inlineElements[$this->name])) {
			return Texy::CONTENT_BLOCK;
		}

		return self::$inlineElements[$this->name] ? Texy::CONTENT_REPLACED : Texy::CONTENT_MARKUP;
	}


	final public function validateAttrs(array $dtd): void
	{
		if (isset($dtd[$this->name])) {
			$allowed = $dtd[$this->name][0];
			if (is_array($allowed)) {
				foreach ($this->attrs as $attr => $foo) {
					if (!isset($allowed[$attr]) && (!isset($allowed['data-*']) || substr($attr, 0, 5) !== 'data-')) {
						unset($this->attrs[$attr]);
					}
				}
			}
		}
	}


	public function validateChild($child, array $dtd): bool
	{
		if (isset($dtd[$this->name])) {
			if ($child instanceof self) {
				$child = $child->name;
			}
			return isset($dtd[$this->name][1][$child]);
		} else {
			return true; // unknown element
		}
	}


	/**
	 * Parses text as single line.
	 */
	final public function parseLine(Texy $texy, string $s): LineParser
	{
		$parser = new LineParser($texy, $this);
		$parser->parse($s);
		return $parser;
	}


	/**
	 * Parses text as block.
	 */
	final public function parseBlock(Texy $texy, string $s, bool $indented = false): void
	{
		$parser = new BlockParser($texy, $this, $indented);
		$parser->parse($s);
	}
}
