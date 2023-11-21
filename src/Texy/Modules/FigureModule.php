<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Patterns;


/**
 * The captioned figures.
 */
final class FigureModule extends Texy\Module
{
	/** non-floated box CSS class */
	public ?string $class = 'figure';

	/** left-floated box CSS class */
	public ?string $leftClass = null;

	/** right-floated box CSS class */
	public ?string $rightClass = null;

	/** how calculate div's width */
	public int|false $widthDelta = 10;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('figure', $this->solve(...));

		$texy->registerBlockPattern(
			$this->pattern(...),
			'#^\[\* *+([^\n' . Patterns::MARK . ']{1,1000})' . Patterns::MODIFIER . '? *+(\*|(?<!<)>|<)\]' // [* urls .(title)[class]{style} >]
			. '(?::(' . Patterns::LINK_URL . '|:))?? ++\*\*\* ++(.{0,2000})' . Patterns::MODIFIER_H . '?()$#mUu',
			'figure',
		);
	}


	/**
	 * Callback for [*image*]:link *** .... .(title)[class]{style}>.
	 */
	public function pattern(Texy\BlockParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]
		// [5] => ...
		// [6] => .(title)[class]{style}<>

		$texy = $this->texy;
		$image = $texy->imageModule->factoryImage($mURLs, $mImgMod . $mAlign);
		$mod = new Texy\Modifier($mMod);
		$mContent = ltrim($mContent);

		if ($mLink) {
			if ($mLink === ':') {
				$link = new Texy\Link($image->linkedURL ?? $image->URL);
				$link->raw = ':';
				$link->type = $link::IMAGE;
			} else {
				$link = $texy->linkModule->factoryLink($mLink, null, null);
			}
		} else {
			$link = null;
		}

		return $texy->invokeAroundHandlers('figure', $parser, [$image, $link, $mContent, $mod]);
	}


	/**
	 * Finish invocation.
	 */
	private function solve(
		Texy\HandlerInvocation $invocation,
		Texy\Image $image,
		?Texy\Link $link,
		string $content,
		Texy\Modifier $mod,
	): ?Texy\HtmlElement
	{
		$texy = $this->texy;

		$hAlign = $image->modifier->hAlign;
		$image->modifier->hAlign = null;

		$elImg = $texy->imageModule->solve(null, $image, $link); // returns Texy\HtmlElement or null!
		if (!$elImg) {
			return null;
		}

		$el = new Texy\HtmlElement('div');
		if (!empty($image->width) && $this->widthDelta !== false) {
			$el->attrs['style']['max-width'] = ($image->width + $this->widthDelta) . 'px';
		}

		$mod->decorate($texy, $el);

		$el[0] = $elImg;
		$el[1] = new Texy\HtmlElement('p');
		$el[1]->parseLine($texy, ltrim($content));

		$class = $this->class;
		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$class = $this->$var;

			} elseif (empty($texy->alignClasses[$hAlign])) {
				$el->attrs['style']['float'] = $hAlign;

			} else {
				$class .= '-' . $texy->alignClasses[$hAlign];
			}
		}

		$el->attrs['class'][] = $class;

		return $el;
	}
}
