<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\FigureNode;
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
		$texy->addHandler(FigureNode::class, $this->toElement(...));

		// [* urls .(title)[class]{style} >]
		$texy->registerBlockPattern(
			$this->parse(...),
			'~^
				\[\*\ *+                          # opening bracket with asterisk
				([^\n' . Patterns::MARK . ']{1,1000}) # URLs (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				\ *+
				( \* | (?<! < ) > | < )           # alignment (3)
				]
				(?:
					:(' . Patterns::LINK_URL . ' | : ) # link or colon (4)
				)??
				\ ++ \*\*\* \ ++                  # separator
				(.{0,2000})                       # figure content (5)
				' . Patterns::MODIFIER_H . '?     # modifier (6)
			$~mU',
			'figure',
		);
	}


	/**
	 * Callback for [*image*]:link *** .... .(title)[class]{style}>.
	 */
	public function parse(Texy\BlockParser $parser, array $matches): FigureNode
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]
		// [5] => ...
		// [6] => .(title)[class]{style}<>

		$texy = $parser->getTexy();
		$image = $texy->imageModule->factoryImage($mURLs, $mImgMod . $mAlign);
		$link = null;
		if ($mLink === ':') {
			$link = new Texy\Link($image->linkedURL ?? $image->URL);
			$link->raw = ':';
			$link->type = $link::IMAGE;
		} elseif ($mLink) {
			$link = $texy->linkModule->factoryLink($mLink, null, null);
		}

		return new FigureNode(
			$texy->parseBlock(ltrim($mContent)),
			$image,
			$mMod ? new Texy\Modifier($mMod) : null,
			$link,
		);
	}


	private function toElement(FigureNode $figure, Texy\Texy $texy): ?Texy\HtmlElement
	{
		$hAlign = $figure->image->modifier->hAlign;
		$figure->image->modifier->hAlign = null;

		$elImg = $texy->imageModule->toElement(null, $figure->image, $figure->link); // returns Texy\HtmlElement or null!
		if (!$elImg) {
			return null;
		}

		$el = new Texy\HtmlElement('div');
		if (!empty($figure->image->width) && $this->widthDelta !== false) {
			$el->attrs['style']['max-width'] = ($figure->image->width + $this->widthDelta) . 'px';
		}

		$el->add($elImg);
		$el->inject($texy, $figure->content, $figure->modifier);

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
