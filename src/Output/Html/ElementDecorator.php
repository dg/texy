<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{HtmlPolicy, Modifier};


/**
 * Applies Modifier (classes, styles, attributes, alignment) to an Element.
 *
 * This is where the security policy is enforced, and the placement is deliberate:
 * it is the moment an author-written modifier is poured into the output element.
 * Anything a transform pass or a renderer handler writes into the Element itself
 * never passes through here, so generated markup is not subject to the policy.
 */
final class ElementDecorator
{
	public function __construct(
		private HtmlPolicy $policy,
		private Config $config,
	) {
	}


	/**
	 * Decorate element with modifier's classes, styles, and attributes.
	 */
	public function decorate(?Modifier $modifier, Element $el): void
	{
		if ($modifier === null) {
			return;
		}

		$this->decorateAttrs($modifier, $el);
		$this->decorateClasses($modifier, $el->attrs);
		$this->decorateStyles($modifier, $el->attrs);
		$this->decorateAligns($modifier, $el->attrs);
	}


	private function decorateAttrs(Modifier $modifier, Element $el): void
	{
		$attrs = &$el->attrs;

		if ($modifier->attrs) {
			$attrs = $this->policy->filterUrls($this->policy->filterAttrs($el->name ?? '', $modifier->attrs));
		}

		if ($modifier->title !== null) {
			$attrs['title'] = $modifier->title;
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateClasses(Modifier $modifier, array &$attrs): void
	{
		foreach ($modifier->classes as $class => $foo) {
			if ($this->policy->isClassAllowed($class)) {
				$attrs['class'] = (array) ($attrs['class'] ?? []);
				$attrs['class'][] = $class;
			}
		}

		if ($modifier->id !== null && $this->policy->isIdAllowed($modifier->id)) {
			$attrs['id'] = $modifier->id;
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateStyles(Modifier $modifier, array &$attrs): void
	{
		foreach ($modifier->styles as $prop => $value) {
			if ($this->policy->isStyleAllowed($prop)) {
				$attrs['style'] = (array) ($attrs['style'] ?? []);
				$attrs['style'][$prop] = $value;
			}
		}
	}


	/**
	 * Applies the float of an image or a figure: its class, or the inline style.
	 * @param  'left'|'right'  $float
	 */
	public function applyFloat(Element $el, string $float): void
	{
		self::applyClassOrStyle($el->attrs, $this->config->floatClasses[$float] ?? null, 'float', $float);
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateAligns(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->hAlign) {
			$class = $this->config->textAlignClasses[$modifier->hAlign] ?? null;
			self::applyClassOrStyle($attrs, $class, 'text-align', $modifier->hAlign);
		}

		if ($modifier->vAlign) {
			$class = $this->config->verticalAlignClasses[$modifier->vAlign] ?? null;
			self::applyClassOrStyle($attrs, $class, 'vertical-align', $modifier->vAlign);
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private static function applyClassOrStyle(array &$attrs, ?string $class, string $property, string $value): void
	{
		if ($class) {
			$attrs['class'] = (array) ($attrs['class'] ?? []);
			$attrs['class'][] = $class;
		} else {
			$attrs['style'] = (array) ($attrs['style'] ?? []);
			$attrs['style'][$property] = $value;
		}
	}
}
