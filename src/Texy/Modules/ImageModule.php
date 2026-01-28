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


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['image/definition'] = true;
		$texy->addHandler('image', $this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		// [*image*]:LINK
		$this->texy->registerLinePattern(
			$this->parseImage(...),
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
			~U',
			'image',
		);

		if (!empty($this->texy->allowed['image/definition'])) {
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
				$~mU',
				$this->parseDefinition(...),
			);
		}
	}


	/**
	 * Parses [*image*]: urls .(title)[class]{style}
	 * @param  array<?string>  $matches
	 */
	private function parseDefinition(array $matches): string
	{
		/** @var array{string, string, string, ?string} $matches */
		[, $mRef, $mURLs, $mMod] = $matches;
		// [1] => [* (reference) *]
		// [2] => urls
		// [3] => .(title)[class]{style}<>

		$image = $this->factoryImage($mURLs, $mMod, tryRef: false);
		$image->name = Helpers::toLower($mRef);
		$this->references[$image->name] = $image;
		return '';
	}


	/**
	 * Parses [* small.jpg 80x13 || big.jpg .(alternative text)[class]{style}>]:LINK
	 * @param  array<?string>  $matches
	 */
	public function parseImage(Texy\InlineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		/** @var array{string, string, ?string, string, ?string} $matches */
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
	 * Adds a user-defined image definition (persists across process() calls).
	 */
	public function addDefinition(
		string $name,
		string $url,
		?int $width = null,
		?int $height = null,
		?string $alt = null,
	): void
	{
		$image = new Image;
		$image->URL = $url;
		$image->width = $width;
		$image->height = $height;
		if ($alt !== null) {
			$image->modifier->title = $alt;
		}
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
	public function factoryImage(string $content, ?string $mod, bool $tryRef = true): Image
	{
		$image = $tryRef ? $this->getReference(trim($content)) : null;

		if (!$image) {
			$texy = $this->texy;
			$content = explode('|', $content);
			$image = new Image;

			// dimensions
			$matches = null;
			if ($matches = Texy\Regexp::match($content[0], '~^(.*)\ (\d+|\?)\ *([Xx])\ *(\d+|\?)\ *$~U')) {
				/** @var array{string, string, string, string, string} $matches */
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
