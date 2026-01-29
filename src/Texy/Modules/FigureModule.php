<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Patterns;
use function trim;


/**
 * Processes images with captions.
 */
final class FigureModule extends Texy\Module
{
	public string $tagName = 'div';

	/** non-floated box CSS class */
	public ?string $class = 'figure';

	/** left-floated box CSS class */
	public ?string $leftClass = null;

	/** right-floated box CSS class */
	public ?string $rightClass = null;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler('figure', $this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				(?>
					\[\*\ *+                      # opening bracket with asterisk
					([^\n' . Patterns::MARK . ']{1,1000}) # URLs (1)
					' . Patterns::MODIFIER . '?   # modifier (2)
					\ *+
					( \* | (?<! < ) > | < )       # alignment (3)
					]
				)
				(?:
					:(' . Patterns::LINK_URL . ' | : ) # link or colon (4)
				)??
				(?:
					\ ++ \*\*\* \ ++              # separator
					(.{0,2000})                   # caption (5)
				)?
				' . Patterns::MODIFIER_H . '?     # modifier (6)
			$~mU',
			'figure',
		);
	}


	/**
	 * Parses [*image*]:link *** caption .(title)[class]{style}>.
	 * @param  array<?string>  $matches
	 */
	public function parse(Texy\BlockParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		/** @var array{string, string, ?string, string, ?string, ?string, ?string} $matches */
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]
		// [5] => ...
		// [6] => .(title)[class]{style}<>

		$texy = $this->texy;
		$image = $texy->imageModule->factoryImage($mURLs, $mImgMod . $mAlign);
		$mod = Texy\Modifier::parse($mMod);
		$mContent = trim($mContent ?? '');

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

		$el = new Texy\HtmlElement($this->tagName);
		$mod->decorate($texy, $el);

		$el->add($elImg);

		if ($content !== '') {
			$el[1] = new Texy\HtmlElement($this->tagName === 'figure' ? 'figcaption' : 'p');
			$el[1]->parseLine($texy, $content);
		}

		$class = $this->class;
		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$class = $this->$var;

			} elseif (empty($texy->alignClasses[$hAlign])) {
				settype($el->attrs['style'], 'array');
				$el->attrs['style']['float'] = $hAlign;

			} else {
				$class .= '-' . $texy->alignClasses[$hAlign];
			}
		}

		settype($el->attrs['class'], 'array');
		$el->attrs['class'][] = $class;

		return $el;
	}
}
