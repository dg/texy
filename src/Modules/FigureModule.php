<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * The captioned figures.
 *
 * @author     David Grudl
 */
final class FigureModule extends Texy\Module
{
	/** @var string  non-floated box CSS class */
	public $class = 'figure';

	/** @var string  left-floated box CSS class */
	public $leftClass;

	/** @var string  right-floated box CSS class */
	public $rightClass;

	/** @var int  how calculate div's width */
	public $widthDelta = 10;


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('figure', array($this, 'solve'));

		$texy->registerBlockPattern(
			array($this, 'pattern'),
			'#^\[\* *+([^\n'.Texy\Patterns::MARK.']{1,1000})'.Texy\Patterns::MODIFIER.'? *+(\*|(?<!<)>|<)\]' // [* urls .(title)[class]{style} >]
			. '(?::('.Texy\Patterns::LINK_URL.'|:))?? ++\*\*\* ++(.{0,2000})'.Texy\Patterns::MODIFIER_H.'?()$#mUu',
			'figure'
		);
	}


	/**
	 * Callback for [*image*]:link *** .... .(title)[class]{style}>.
	 *
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function pattern($parser, $matches)
	{
		list(, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod) = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]
		// [5] => ...
		// [6] => .(title)[class]{style}<>

		$tx = $this->texy;
		$image = $tx->imageModule->factoryImage($mURLs, $mImgMod.$mAlign);
		$mod = new Texy\Modifier($mMod);
		$mContent = ltrim($mContent);

		if ($mLink) {
			if ($mLink === ':') {
				$link = new Link($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
				$link->raw = ':';
				$link->type = Link::IMAGE;
			} else {
				$link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
			}
		} else {
			$link = NULL;
		}

		return $tx->invokeAroundHandlers('figure', $parser, array($image, $link, $mContent, $mod));
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Image
	 * @param  Link
	 * @param  string
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement|FALSE
	 */
	public function solve($invocation, Image $image, $link, $content, $mod)
	{
		$tx = $this->texy;

		$hAlign = $image->modifier->hAlign;
		$image->modifier->hAlign = NULL;

		$elImg = $tx->imageModule->solve(NULL, $image, $link); // returns Texy\HtmlElement or false!
		if (!$elImg) {
			return FALSE;
		}

		$el = Texy\HtmlElement::el('div');
		if (!empty($image->width) && $this->widthDelta !== FALSE) {
			$el->attrs['style']['width'] = ($image->width + $this->widthDelta) . 'px';
		}
		$mod->decorate($tx, $el);

		$el[0] = $elImg;
		$el[1] = Texy\HtmlElement::el('p');
		$el[1]->parseLine($tx, ltrim($content));

		$class = $this->class;
		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$class = $this->$var;

			} elseif (empty($tx->alignClasses[$hAlign])) {
				$el->attrs['style']['float'] = $hAlign;

			} else {
				$class .= '-' . $tx->alignClasses[$hAlign];
			}
		}
		$el->attrs['class'][] = $class;

		return $el;
	}

}
