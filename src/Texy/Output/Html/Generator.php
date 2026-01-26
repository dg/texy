<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Output\Html;

use Texy\Helpers;
use Texy\Modifier;
use Texy\Node;
use Texy\Nodes;
use Texy\Texy;
use function in_array, is_array;


/**
 * Generates HTML output from AST.
 */
class Generator
{
	/** @var array<class-string<Node>, \Closure(Node, self): string> */
	protected array $handlers = [];


	public function __construct(
		private Texy $texy,
	) {
		$this->handlers = [
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $node, self $generator) => $generator->generateDocument($node),
			Nodes\TextNode::class => fn(Nodes\TextNode $node, self $generator) => $generator->generateText($node),
		];
	}


	/**
	 * Register a handler for a node class (replaces existing handler).
	 * @param \Closure(Node, self): string $handler
	 */
	public function registerHandler(\Closure $handler): void
	{
		$nodeClass = (string) (new \ReflectionFunction($handler))->getParameters()[0]->getType();
		$this->handlers[$nodeClass] = $handler;
	}


	public function generateNode(Node $node): string
	{
		$handler = $this->handlers[$node::class] ?? null;
		return $handler($node, $this);
	}


	/**
	 * @param array<Nodes\InlineNode> $content
	 */
	public function generateInlineContent(array $content): string
	{
		$html = [];
		foreach ($content as $child) {
			$html[] = $this->generateNode($child);
		}
		return implode('', $html);
	}


	/**
	 * @param array<Nodes\BlockNode> $content
	 */
	public function generateBlockContent(array $content): string
	{
		$html = [];
		foreach ($content as $child) {
			$html[] = $this->generateNode($child);
		}
		return implode("\n", $html);
	}


	protected function generateDocument(Nodes\DocumentNode $node): string
	{
		$html = [];
		foreach ($node->content as $child) {
			$html[] = $this->generateNode($child);
		}
		return implode("\n\n", $html);
	}


	protected function generateText(Nodes\TextNode $node): string
	{
		// Return raw text - escaping happens after typography in unprotect()
		return $node->content;
	}


	/**
	 * @param array<string, string|bool|null> $attrs
	 */
	public function generateAttrs(array $attrs): string
	{
		$html = [];
		foreach ($attrs as $name => $value) {
			if ($value === null || $value === false) {
				continue;
			} elseif ($value === true) {
				$html[] = $name;
			} else {
				// Escape same characters as HtmlElement::startTag() - NOT single quotes
				$value = str_replace(['&', '"', '<', '>', '@'], ['&amp;', '&quot;', '&lt;', '&gt;', '&#64;'], $value);
				// Freeze spaces in attribute values to prevent line breaking inside attributes
				$value = Helpers::freezeSpaces($value);
				$html[] = $name . '="' . $value . '"';
			}
		}
		return $html ? ' ' . implode(' ', $html) : '';
	}


	public function generateModifierAttrs(?Modifier $modifier): string
	{
		if ($modifier === null) {
			return '';
		}

		$classes = [];
		$style = [];

		// Filter classes
		foreach (array_keys($modifier->classes) as $class) {
			if ($this->isClassAllowed($class)) {
				$classes[] = $class;
			}
		}

		// Horizontal alignment
		if ($modifier->hAlign && $this->texy) {
			$hAlignClass = $this->texy->alignClasses[$modifier->hAlign] ?? null;
			if ($hAlignClass) {
				$classes[] = $hAlignClass;
			} else {
				$style[] = 'text-align:' . $modifier->hAlign;
			}
		}

		// Vertical alignment
		if ($modifier->vAlign && $this->texy) {
			$vAlignClass = $this->texy->alignClasses[$modifier->vAlign] ?? null;
			if ($vAlignClass) {
				$classes[] = $vAlignClass;
			} else {
				$style[] = 'vertical-align:' . $modifier->vAlign;
			}
		}

		// Filter styles
		foreach ($modifier->styles as $prop => $value) {
			if ($this->isStyleAllowed($prop)) {
				$style[] = $prop . ':' . $value;
			}
		}

		// Build attrs in correct order: title, class, id, custom attrs, style
		$attrs = [];

		if ($modifier->title !== null) {
			$attrs['title'] = $modifier->title;
		}

		if ($classes) {
			$attrs['class'] = implode(' ', $classes);
		}

		if ($modifier->id && $this->isClassAllowed('#' . $modifier->id)) {
			$attrs['id'] = $modifier->id;
		}

		// Custom attributes (data-*, aria-*, etc.)
		foreach ($modifier->attrs as $key => $value) {
			$attrs[$key] = $value;
		}

		if ($style) {
			$attrs['style'] = implode(';', $style);
		}

		return $this->generateAttrs($attrs);
	}


	/**
	 * Check if class/ID is allowed based on Texy::$allowedClasses
	 */
	protected function isClassAllowed(string $class): bool
	{
		if ($this->texy === null) {
			return true;
		}

		$allowed = $this->texy->allowedClasses;

		if ($allowed === Texy::ALL) {
			return true;
		}

		if ($allowed === Texy::NONE || $allowed === false) {
			return false;
		}

		if (is_array($allowed)) {
			return in_array($class, $allowed, true);
		}

		return (bool) $allowed;
	}


	/**
	 * Check if style property is allowed based on Texy::$allowedStyles
	 */
	protected function isStyleAllowed(string $property): bool
	{
		if ($this->texy === null) {
			return true;
		}

		$allowed = $this->texy->allowedStyles;

		if ($allowed === Texy::ALL) {
			return true;
		}

		if ($allowed === Texy::NONE || $allowed === false) {
			return false;
		}

		if (is_array($allowed)) {
			return in_array($property, $allowed, true);
		}

		return (bool) $allowed;
	}
}
