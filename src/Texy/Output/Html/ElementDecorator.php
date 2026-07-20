<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Modifier;
use Texy\Texy;
use function array_flip, is_array, settype;


/**
 * Applies Modifier (classes, styles, attributes, alignment) to an Element,
 * filtered by the Policy whitelists.
 */
final class ElementDecorator
{
	public function __construct(
		private Texy $texy,
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
		$name = $el->name ?? '';

		if (!$modifier->attrs) {
		} elseif ($this->texy->htmlPolicy->allowedTags === Texy::ALL) {
			$attrs = $modifier->attrs;

		} elseif (is_array($this->texy->htmlPolicy->allowedTags)) {
			$tmp = $this->texy->htmlPolicy->allowedTags[$name] ?? [];

			if ($tmp === Texy::ALL) {
				$attrs = $modifier->attrs;

			} elseif (is_array($tmp)) {
				$attrs = array_flip($tmp);
				foreach ($modifier->attrs as $key => $value) {
					if (isset($attrs[$key])) {
						$attrs[$key] = $value;
					}
				}
			}
		}

		if ($modifier->title !== null) {
			$attrs['title'] = $this->texy->typographyModule->postLine($modifier->title);
		}
	}


	/** @param  array<string, mixed>  $attrs */
	private function decorateClasses(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->classes || $modifier->id !== null) {
			[$allowedClasses] = $this->texy->htmlPolicy->getAllowedProps();
			settype($attrs['class'], 'array');
			if ($allowedClasses === Texy::ALL) {
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
			[, $allowedStyles] = $this->texy->htmlPolicy->getAllowedProps();
			settype($attrs['style'], 'array');
			if ($allowedStyles === Texy::ALL) {
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


	/** @param  array<string, mixed>  $attrs */
	private function decorateAligns(Modifier $modifier, array &$attrs): void
	{
		if ($modifier->hAlign) {
			$class = $this->config->alignClasses[$modifier->hAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['text-align'] = $modifier->hAlign;
			}
		}

		if ($modifier->vAlign) {
			$class = $this->config->alignClasses[$modifier->vAlign] ?? null;
			if ($class) {
				settype($attrs['class'], 'array');
				$attrs['class'][] = $class;
			} else {
				settype($attrs['style'], 'array');
				$attrs['style']['vertical-align'] = $modifier->vAlign;
			}
		}
	}
}
