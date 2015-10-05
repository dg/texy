<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Images module.
 */
final class TexyImageModule extends TexyModule
{
	/** @var string  root of relative images (http) */
	public $root = 'images/';

	/** @var string  root of linked images (http) */
	public $linkedRoot = 'images/';

	/** @var string  physical location of images on server */
	public $fileRoot = NULL;

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

		$texy->allowed['image/definition'] = TRUE;
		$texy->allowed['image/hover'] = TRUE;
		$texy->addHandler('image', [$this, 'solve']);
		$texy->addHandler('beforeParse', [$this, 'beforeParse']);

		// [*image*]:LINK
		$texy->registerLinePattern(
			[$this, 'patternImage'],
			'#\[\* *+([^\n'.TexyPatterns::MARK.']{1,1000})'.TexyPatterns::MODIFIER.'? *+(\*|(?<!<)>|<)\]' // [* urls .(title)[class]{style} >]
			. '(?::('.TexyPatterns::LINK_URL.'|:))??()#Uu',
			'image'
		);
	}


	/**
	 * Text pre-processing.
	 * @param  Texy
	 * @param  string
	 * @return void
	 */
	public function beforeParse($texy, & $text)
	{
		if (!empty($texy->allowed['image/definition'])) {
			// [*image*]: urls .(title)[class]{style}
			$text = TexyRegexp::replace(
				$text,
				'#^\[\*([^\n]{1,100})\*\]:\ +(.{1,1000})\ *'.TexyPatterns::MODIFIER.'?\s*()$#mUu',
				[$this, 'patternReferenceDef']
			);
		}
	}


	/**
	 * Callback for: [*image*]: urls .(title)[class]{style}.
	 *
	 * @param  array      regexp matches
	 * @return string
	 * @internal
	 */
	public function patternReferenceDef($matches)
	{
		list(, $mRef, $mURLs, $mMod) = $matches;
		// [1] => [* (reference) *]
		// [2] => urls
		// [3] => .(title)[class]{style}<>

		$image = $this->factoryImage($mURLs, $mMod, FALSE);
		$this->addReference($mRef, $image);
		return '';
	}


	/**
	 * Callback for [* small.jpg 80x13 | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK.
	 *
	 * @param  TexyLineParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return TexyHtml|string|FALSE
	 */
	public function patternImage($parser, $matches)
	{
		list(, $mURLs, $mMod, $mAlign, $mLink) = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]

		$tx = $this->texy;

		$image = $this->factoryImage($mURLs, $mMod.$mAlign);

		if ($mLink) {
			if ($mLink === ':') {
				$link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
				$link->raw = ':';
				$link->type = TexyLink::IMAGE;
			} else {
				$link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
			}
		} else {
			$link = NULL;
		}

		return $tx->invokeAroundHandlers('image', $parser, [$image, $link]);
	}


	/**
	 * Adds new named reference to image.
	 *
	 * @param  string  reference name
	 * @param  TexyImage
	 * @return void
	 */
	public function addReference($name, TexyImage $image)
	{
		$image->name = TexyUtf::strtolower($name);
		$this->references[$image->name] = $image;
	}


	/**
	 * Returns named reference.
	 *
	 * @param  string  reference name
	 * @return TexyImage  reference descriptor (or FALSE)
	 */
	public function getReference($name)
	{
		$name = TexyUtf::strtolower($name);
		if (isset($this->references[$name])) {
			return clone $this->references[$name];
		}

		return FALSE;
	}


	/**
	 * Parses image's syntax.
	 * @param  string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
	 * @param  string
	 * @param  bool
	 * @return TexyImage
	 */
	public function factoryImage($content, $mod, $tryRef = TRUE)
	{
		$image = $tryRef ? $this->getReference(trim($content)) : FALSE;

		if (!$image) {
			$tx = $this->texy;
			$content = explode('|', $content);
			$image = new TexyImage;

			// dimensions
			$matches = NULL;
			if ($matches = TexyRegexp::match($content[0], '#^(.*) (\d+|\?) *(X|x) *(\d+|\?) *()$#U')) {
				$image->URL = trim($matches[1]);
				$image->asMax = $matches[3] === 'X';
				$image->width = $matches[2] === '?' ? NULL : (int) $matches[2];
				$image->height = $matches[4] === '?' ? NULL : (int) $matches[4];
			} else {
				$image->URL = trim($content[0]);
			}

			if (!$tx->checkURL($image->URL, Texy::FILTER_IMAGE)) {
				$image->URL = NULL;
			}

			// onmouseover image
			if (isset($content[1])) {
				$tmp = trim($content[1]);
				if ($tmp !== '' && $tx->checkURL($tmp, Texy::FILTER_IMAGE)) {
					$image->overURL = $tmp;
				}
			}

			// linked image
			if (isset($content[2])) {
				$tmp = trim($content[2]);
				if ($tmp !== '' && $tx->checkURL($tmp, Texy::FILTER_ANCHOR)) {
					$image->linkedURL = $tmp;
				}
			}
		}

		$image->modifier->setProperties($mod);
		return $image;
	}


	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyImage
	 * @param  TexyLink
	 * @return TexyHtml|FALSE
	 */
	public function solve($invocation, TexyImage $image, $link)
	{
		if ($image->URL == NULL) {
			return FALSE;
		}

		$tx = $this->texy;

		$mod = $image->modifier;
		$alt = $mod->title;
		$mod->title = NULL;
		$hAlign = $mod->hAlign;
		$mod->hAlign = NULL;

		$el = TexyHtml::el('img');
		$el->attrs['src'] = NULL; // trick - move to front
		$mod->decorate($tx, $el);
		$el->attrs['src'] = Texy::prependRoot($image->URL, $this->root);
		if (!isset($el->attrs['alt'])) {
			$el->attrs['alt'] = $alt === NULL ? $this->defaultAlt : $tx->typographyModule->postLine($alt);
		}

		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$el->attrs['class'][] = $this->$var;

			} elseif (empty($tx->alignClasses[$hAlign])) {
				$el->attrs['style']['float'] = $hAlign;

			} else {
				$el->attrs['class'][] = $tx->alignClasses[$hAlign];
			}
		}

		if (!is_int($image->width) || !is_int($image->height) || $image->asMax) {
			// autodetect fileRoot
			if ($this->fileRoot === NULL && isset($_SERVER['SCRIPT_FILENAME'])) {
				$this->fileRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->root;
			}

			// detect dimensions
			// absolute URL & security check for double dot
			if (Texy::isRelative($image->URL) && strpos($image->URL, '..') === FALSE) {
				$file = rtrim($this->fileRoot, '/\\') . '/' . $image->URL;
				if (@is_file($file)) { // intentionally @
					$size = @getImageSize($file); // intentionally @
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
		if (!empty($tx->allowed['image/hover']) && $image->overURL !== NULL) {
			$overSrc = Texy::prependRoot($image->overURL, $this->root);
			$el->attrs['onmouseover'] = 'this.src=\'' . addSlashes($overSrc) . '\'';
			$el->attrs['onmouseout'] = 'this.src=\'' . addSlashes($el->attrs['src']) . '\'';
			$el->attrs['onload'] = str_replace('%i', addSlashes($overSrc), $this->onLoad);
			$tx->summary['preload'][] = $overSrc;
		}

		$tx->summary['images'][] = $el->attrs['src'];

		if ($link) {
			return $tx->linkModule->solve(NULL, $link, $el);
		}

		return $el;
	}

}
