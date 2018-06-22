<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Patterns;


/**
 * The captioned figures.
 */
final class FigureModule extends Texy\Module
{
	/** @var string  non-floated box CSS class */
	public $class = 'figure';

	/** @var string  left-floated box CSS class */
	public $leftClass;

	/** @var string  right-floated box CSS class */
	public $rightClass;

	/** @var int|false  how calculate div's width */
	public $widthDelta = 10;


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('figure', [$this, 'solve']);

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^\[\* *+([^\n' . Patterns::MARK . ']{1,1000})' . Patterns::MODIFIER . '? *+(\*|(?<!<)>|<)\]' // [* urls .(title)[class]{style} >]
			. '(?::(' . Patterns::LINK_URL . '|:))?? ++\*\*\* ++(.{0,2000})' . Patterns::MODIFIER_H . '?()$#mUu',
			'figure'
		);
	}


	/**
	 * Callback for [*image*]:link *** .... .(title)[class]{style}>.
	 * @return Texy\HtmlElement|string|false
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		list(, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod) = $matches;
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
				$link = new Texy\Link($image->linkedURL === null ? $image->URL : $image->linkedURL);
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
	 * @return Texy\HtmlElement|false
	 */
	public function solve(Texy\HandlerInvocation $invocation, Texy\Image $image, Texy\Link $link = null, $content, Texy\Modifier $mod)
	{
		$texy = $this->texy;

		$hAlign = $image->modifier->hAlign;
		$image->modifier->hAlign = null;

		$elImg = $texy->imageModule->solve(null, $image, $link); // returns Texy\HtmlElement or false!
		if (!$elImg) {
			return false;
		}

		$el = new Texy\HtmlElement('div');
		if (!empty($image->width) && $this->widthDelta !== false) {
			$el->attrs['style']['width'] = ($image->width + $this->widthDelta) . 'px';
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
