<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{HtmlPolicy, Modifier, UrlPolicy};
use function is_array, is_string;


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
	/** @var array<string, int>|bool  lookup set snapshot of $policy->allowedClasses */
	private bool|array $classes;

	/** @var array<string, int>|bool  lookup set snapshot of $policy->allowedStyles */
	private bool|array $styles;


	public function __construct(
		private HtmlPolicy $policy,
		private UrlPolicy $urlPolicy,
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
			$attrs = $this->filterUrls($this->policy->filterAttrs($el->name ?? '', $modifier->attrs));
		}

		if ($modifier->title !== null) {
			$attrs['title'] = $modifier->title;
		}
	}


	/**
	 * A URL attribute survives only if UrlPolicy accepts it. This runs regardless of
	 * the whitelist - "javascript:" must not get through even on a permissive policy.
	 * @param  array<string, mixed>  $attrs
	 * @return array<string, mixed>
	 */
	private function filterUrls(array $attrs): array
	{
		foreach ($attrs as $name => $value) {
			$kind = Schema::urlAttribute($name);
			if ($kind === null || !is_string($value)) {
				continue;
			}

			$allowed = $kind === Schema::UrlImage
				? $this->urlPolicy->isImageAllowed($value)
				: $this->urlPolicy->isLinkAllowed($value);
			if (!$allowed) {
				unset($attrs[$name]);
			}
		}

		return $attrs;
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateClasses(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->classes || $modifier->id !== null) {
			$allowedClasses = $this->classes ??= is_array($this->policy->allowedClasses)
				? array_flip($this->policy->allowedClasses)
				: $this->policy->allowedClasses;
			settype($attrs['class'], 'array');
			if ($allowedClasses === true) {
				foreach ($modifier->classes as $value => $foo) {
					$attrs['class'][] = $value;
				}

				$attrs['id'] = $modifier->id;

			} elseif (is_array($allowedClasses)) {
				foreach ($modifier->classes as $value => $foo) {
					if (isset($allowedClasses[$value])) {
						$attrs['class'][] = $value;
					}
				}

				if (isset($allowedClasses['#' . $modifier->id])) {
					$attrs['id'] = $modifier->id;
				}
			}
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateStyles(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->styles) {
			$allowedStyles = $this->styles ??= is_array($this->policy->allowedStyles)
				? array_flip($this->policy->allowedStyles)
				: $this->policy->allowedStyles;
			settype($attrs['style'], 'array');
			if ($allowedStyles === true) {
				foreach ($modifier->styles as $prop => $value) {
					$attrs['style'][$prop] = $value;
				}
			} elseif (is_array($allowedStyles)) {
				foreach ($modifier->styles as $prop => $value) {
					if (isset($allowedStyles[$prop])) {
						$attrs['style'][$prop] = $value;
					}
				}
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
