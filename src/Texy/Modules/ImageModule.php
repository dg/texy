<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Image;
use Texy\Patterns;


/**
 * Images module.
 */
final class ImageModule extends Texy\Module
{
	/** @var string  root of relative images (http) */
	public $root = 'images/';

	/** @var string  root of linked images (http) */
	public $linkedRoot = 'images/';

	/** @var string  physical location of images on server */
	public $fileRoot;

	/** @var string  left-floated images CSS class */
	public $leftClass;

	/** @var string  right-floated images CSS class */
	public $rightClass;

	/** @var string  default alternative text */
	public $defaultAlt = '';

	/** @var string  images onload handler */
	public $onLoad = "var i=new Image();i.src='%i';if(typeof preload=='undefined')preload=new Array();preload[preload.length]=i;this.onload=''";

	/** @var array image references */
	private $references = [];


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->allowed['image/definition'] = true;
		$texy->allowed['image/hover'] = true;
		$texy->addHandler('image', [$this, 'solve']);
		$texy->addHandler('beforeParse', [$this, 'beforeParse']);

		// [*image*]:LINK
		$texy->registerLinePattern(
			[$this, 'patternImage'],
			'#\[\* *+([^\n' . Patterns::MARK . ']{1,1000})' . Patterns::MODIFIER . '? *+(\*|(?<!<)>|<)\]' // [* urls .(title)[class]{style} >]
			. '(?::(' . Patterns::LINK_URL . '|:))??()#Uu',
			'image'
		);
	}


	/**
	 * Text pre-processing.
	 * @return void
	 */
	public function beforeParse(Texy\Texy $texy, &$text)
	{
		if (!empty($texy->allowed['image/definition'])) {
			// [*image*]: urls .(title)[class]{style}
			$text = Texy\Regexp::replace(
				$text,
				'#^\[\*([^\n]{1,100})\*\]:\ +(.{1,1000})\ *' . Patterns::MODIFIER . '?\s*()$#mUu',
				[$this, 'patternReferenceDef']
			);
		}
	}


	/**
	 * Callback for: [*image*]: urls .(title)[class]{style}.
	 *
	 * @return string
	 * @internal
	 */
	public function patternReferenceDef(array $matches)
	{
		list(, $mRef, $mURLs, $mMod) = $matches;
		// [1] => [* (reference) *]
		// [2] => urls
		// [3] => .(title)[class]{style}<>

		$image = $this->factoryImage($mURLs, $mMod, false);
		$this->addReference($mRef, $image);
		return '';
	}


	/**
	 * Callback for [* small.jpg 80x13 | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK.
	 * @return Texy\HtmlElement|string|false
	 */
	public function patternImage(Texy\LineParser $parser, array $matches)
	{
		list(, $mURLs, $mMod, $mAlign, $mLink) = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]

		$image = $this->factoryImage($mURLs, $mMod . $mAlign);

		if ($mLink) {
			if ($mLink === ':') {
				$link = new Texy\Link($image->linkedURL === null ? $image->URL : $image->linkedURL);
				$link->raw = ':';
				$link->type = $link::IMAGE;
			} else {
				$link = $this->texy->linkModule->factoryLink($mLink, null, null);
			}
		} else {
			$link = null;
		}

		return $this->texy->invokeAroundHandlers('image', $parser, [$image, $link]);
	}


	/**
	 * Adds new named reference to image.
	 * @return void
	 */
	public function addReference($name, Image $image)
	{
		$image->name = Texy\Utf::strtolower($name);
		$this->references[$image->name] = $image;
	}


	/**
	 * Returns named reference.
	 * @param  string  reference name
	 * @return Image  reference descriptor (or false)
	 */
	public function getReference($name)
	{
		$name = Texy\Utf::strtolower($name);
		if (isset($this->references[$name])) {
			return clone $this->references[$name];
		}

		return false;
	}


	/**
	 * Parses image's syntax.
	 * @param  string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
	 * @param  string
	 * @param  bool
	 * @return Image
	 */
	public function factoryImage($content, $mod, $tryRef = true)
	{
		$image = $tryRef ? $this->getReference(trim($content)) : false;

		if (!$image) {
			$texy = $this->texy;
			$content = explode('|', $content);
			$image = new Image;

			// dimensions
			$matches = null;
			if ($matches = Texy\Regexp::match($content[0], '#^(.*) (\d+|\?) *(X|x) *(\d+|\?) *()$#U')) {
				$image->URL = trim($matches[1]);
				$image->asMax = $matches[3] === 'X';
				$image->width = $matches[2] === '?' ? null : (int) $matches[2];
				$image->height = $matches[4] === '?' ? null : (int) $matches[4];
			} else {
				$image->URL = trim($content[0]);
			}

			if (!$texy->checkURL($image->URL, $texy::FILTER_IMAGE)) {
				$image->URL = null;
			}

			// onmouseover image
			if (isset($content[1])) {
				$tmp = trim($content[1]);
				if ($tmp !== '' && $texy->checkURL($tmp, $texy::FILTER_IMAGE)) {
					$image->overURL = $tmp;
				}
			}

			// linked image
			if (isset($content[2])) {
				$tmp = trim($content[2]);
				if ($tmp !== '' && $texy->checkURL($tmp, $texy::FILTER_ANCHOR)) {
					$image->linkedURL = $tmp;
				}
			}
		}

		$image->modifier->setProperties($mod);
		return $image;
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|false
	 */
	public function solve(Texy\HandlerInvocation $invocation = null, Image $image, Texy\Link $link = null)
	{
		if ($image->URL == null) {
			return false;
		}

		$texy = $this->texy;

		$mod = $image->modifier;
		$alt = $mod->title;
		$mod->title = null;
		$hAlign = $mod->hAlign;
		$mod->hAlign = null;

		$el = new Texy\HtmlElement('img');
		$el->attrs['src'] = null; // trick - move to front
		$mod->decorate($texy, $el);
		$el->attrs['src'] = Helpers::prependRoot($image->URL, $this->root);
		if (!isset($el->attrs['alt'])) {
			$el->attrs['alt'] = $alt === null ? $this->defaultAlt : $texy->typographyModule->postLine($alt);
		}

		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$el->attrs['class'][] = $this->$var;

			} elseif (empty($texy->alignClasses[$hAlign])) {
				$el->attrs['style']['float'] = $hAlign;

			} else {
				$el->attrs['class'][] = $texy->alignClasses[$hAlign];
			}
		}

		if (!is_int($image->width) || !is_int($image->height) || $image->asMax) {
			// autodetect fileRoot
			if ($this->fileRoot === null && isset($_SERVER['SCRIPT_FILENAME'])) {
				$this->fileRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->root;
			}

			// detect dimensions
			// absolute URL & security check for double dot
			if (Helpers::isRelative($image->URL) && strpos($image->URL, '..') === false) {
				$file = rtrim($this->fileRoot, '/\\') . '/' . $image->URL;
				if (@is_file($file)) { // intentionally @
					$size = @getimagesize($file); // intentionally @
					if (is_array($size)) {
						if ($image->asMax) {
							$ratio = 1;
							if (is_int($image->width)) {
								$ratio = min($ratio, $image->width / $size[0]);
							}
							if (is_int($image->height)) {
								$ratio = min($ratio, $image->height / $size[1]);
							}
							$image->width = round($ratio * $size[0]);
							$image->height = round($ratio * $size[1]);

						} elseif (is_int($image->width)) {
							$image->height = round($size[1] / $size[0] * $image->width);

						} elseif (is_int($image->height)) {
							$image->width = round($size[0] / $size[1] * $image->height);

						} else {
							$image->width = $size[0];
							$image->height = $size[1];
						}
					}
				}
			}
		}

		$el->attrs['width'] = $image->width;
		$el->attrs['height'] = $image->height;

		// onmouseover actions generate
		if (!empty($texy->allowed['image/hover']) && $image->overURL !== null) {
			$overSrc = Helpers::prependRoot($image->overURL, $this->root);
			$el->attrs['onmouseover'] = 'this.src=\'' . addslashes($overSrc) . '\'';
			$el->attrs['onmouseout'] = 'this.src=\'' . addslashes($el->attrs['src']) . '\'';
			$el->attrs['onload'] = str_replace('%i', addslashes($overSrc), $this->onLoad);
			$texy->summary['preload'][] = $overSrc;
		}

		$texy->summary['images'][] = $el->attrs['src'];

		if ($link) {
			return $texy->linkModule->solve(null, $link, $el);
		}

		return $el;
	}
}
