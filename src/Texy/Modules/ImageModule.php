<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Image;
use Texy\Patterns;
use function explode, getimagesize, is_file, is_int, min, round, rtrim, str_contains, trim;


/**
 * Processes image syntax and detects image dimensions.
 */
final class ImageModule extends Texy\Module
{
	/** root of relative images (http) */
	public ?string $root = 'images/';

	/** @deprecated */
	public ?string $linkedRoot = 'images/';

	/** physical location of images on server */
	public ?string $fileRoot = null;

	/** left-floated images CSS class */
	public ?string $leftClass = null;

	/** right-floated images CSS class */
	public ?string $rightClass = null;

	/** @deprecated */
	public ?string $defaultAlt = '';

	/** @var array<string, Image> image references */
	private array $references = [];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->allowed['image/definition'] = true;
		$texy->addHandler('image', $this->solve(...));
		$texy->addHandler('beforeParse', $this->beforeParse(...));

		// [*image*]:LINK
		$texy->registerLinePattern(
			$this->patternImage(...),
			'~
				\[\* \ *+                         # opening bracket with asterisk
				([^\n' . Patterns::MARK . ']{1,1000}) # URLs (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				\ *+
				( \* | (?<! < ) > | < )           # alignment (3)
				]
				(?:
					:(' . Patterns::LINK_URL . ' | : ) # link or just colon (4)
				)??
			()~U',
			'image',
		);

	}


	/**
	 * Text pre-processing.
	 */
	private function beforeParse(Texy\Texy $texy, string &$text): void
	{
		if (!empty($texy->allowed['image/definition'])) {
			// [*image*]: urls .(title)[class]{style}
			$text = Texy\Regexp::replace(
				$text,
				'~^
					\[\*                              # opening [*
					( [^\n]{1,100} )                  # reference (1)
					\*]                               # closing *]
					: [ \t]+
					(.{1,1000})                       # URL (2)
					[ \t]*
					' . Patterns::MODIFIER . '?       # modifier (3)
					\s*
				()$~mU',
				$this->patternReferenceDef(...),
			);
		}
	}


	/**
	 * Callback for: [*image*]: urls .(title)[class]{style}.
	 * @param  string[]  $matches
	 */
	private function patternReferenceDef(array $matches): string
	{
		[, $mRef, $mURLs, $mMod] = $matches;
		// [1] => [* (reference) *]
		// [2] => urls
		// [3] => .(title)[class]{style}<>

		$image = $this->factoryImage($mURLs, $mMod, tryRef: false);
		$this->addReference($mRef, $image);
		return '';
	}


	/**
	 * Callback for [* small.jpg 80x13 || big.jpg .(alternative text)[class]{style}>]:LINK.
	 * @param  string[]  $matches
	 */
	public function patternImage(Texy\LineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		[, $mURLs, $mMod, $mAlign, $mLink] = $matches;
		// [1] => URLs
		// [2] => .(title)[class]{style}<>
		// [3] => * < >
		// [4] => url | [ref] | [*image*]

		$image = $this->factoryImage($mURLs, $mMod . $mAlign);

		if ($mLink) {
			if ($mLink === ':') {
				$link = new Texy\Link($image->linkedURL ?? $image->URL);
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
	 */
	public function addReference(string $name, Image $image): void
	{
		$image->name = Helpers::toLower($name);
		$this->references[$image->name] = $image;
	}


	/**
	 * Returns named reference.
	 */
	public function getReference(string $name): ?Image
	{
		$name = Helpers::toLower($name);
		if (isset($this->references[$name])) {
			return clone $this->references[$name];
		}

		return null;
	}


	/**
	 * Parses image's syntax. Input: small.jpg 80x13 || linked.jpg
	 */
	public function factoryImage(string $content, string $mod, bool $tryRef = true): Image
	{
		$image = $tryRef ? $this->getReference(trim($content)) : null;

		if (!$image) {
			$texy = $this->texy;
			$content = explode('|', $content);
			$image = new Image;

			// dimensions
			$matches = null;
			if ($matches = Texy\Regexp::match($content[0], '~^(.*)\ (\d+|\?)\ *([Xx])\ *(\d+|\?)\ *()$~U')) {
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
	 */
	public function solve(
		?Texy\HandlerInvocation $invocation,
		Image $image,
		?Texy\Link $link = null,
	): Texy\HtmlElement|string|null
	{
		if ($image->URL === null) {
			return null;
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
			$el->attrs['alt'] = $alt === null
				? $this->defaultAlt
				: $texy->typographyModule->postLine($alt);
		}

		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				settype($el->attrs['class'], 'array');
				$el->attrs['class'][] = $this->$var;

			} elseif (empty($texy->alignClasses[$hAlign])) {
				settype($el->attrs['style'], 'array');
				$el->attrs['style']['float'] = $hAlign;

			} else {
				settype($el->attrs['class'], 'array');
				$el->attrs['class'][] = $texy->alignClasses[$hAlign];
			}
		}

		if (!is_int($image->width) || !is_int($image->height) || $image->asMax) {
			$this->detectDimensions($image);
		}

		$el->attrs['width'] = $image->width;
		$el->attrs['height'] = $image->height;

		$texy->summary['images'][] = (string) $el->attrs['src'];

		if ($link) {
			return $texy->linkModule->solve(null, $link, $el);
		}

		return $el;
	}


	private function detectDimensions(Image $image): void
	{
		// absolute URL & security check for double dot
		if ($image->URL === null || !Helpers::isRelative($image->URL) || str_contains($image->URL, '..')) {
			return;
		}

		$file = rtrim((string) $this->fileRoot, '/\\') . '/' . $image->URL;
		if (!@is_file($file) || !($size = @getimagesize($file))) { // intentionally @
			return;
		}

		if ($image->asMax) {
			$ratio = 1;
			if (is_int($image->width)) {
				$ratio = min($ratio, $image->width / $size[0]);
			}

			if (is_int($image->height)) {
				$ratio = min($ratio, $image->height / $size[1]);
			}

			$image->width = (int) round($ratio * $size[0]);
			$image->height = (int) round($ratio * $size[1]);

		} elseif (is_int($image->width)) {
			$image->height = (int) round($size[1] / $size[0] * $image->width);

		} elseif (is_int($image->height)) {
			$image->width = (int) round($size[0] / $size[1] * $image->height);

		} else {
			$image->width = $size[0];
			$image->height = $size[1];
		}
	}
}
