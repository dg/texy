<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\BlockParser;
use Texy\Helpers;
use Texy\InlineParser;
use Texy\Modifier;
use Texy\Node;
use Texy\Nodes\DocumentNode;
use Texy\Nodes\FigureNode;
use Texy\Nodes\ImageDefinitionNode;
use Texy\Nodes\ImageNode;
use Texy\NodeTraverser;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use Texy\Regexp;
use function explode, getimagesize, htmlspecialchars, is_file, round, rtrim, str_contains, strlen, trim;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Processes image syntax and detects image dimensions.
 */
final class ImageModule extends Texy\Module
{
	/** root of relative images (http) */
	public ?string $root = 'images/';

	/** root of linked images (http) */
	public ?string $linkedRoot = 'images/';

	/** physical location of images on server */
	public ?string $fileRoot = null;

	/** left-floated images CSS class */
	public ?string $leftClass = null;

	/** right-floated images CSS class */
	public ?string $rightClass = null;

	/** default alternative text */
	public ?string $defaultAlt = '';

	/** @var array<string, ImageDefinitionNode> collected image definitions */
	private array $definitions = [];

	/** @var array<string, ImageDefinitionNode> user-defined definitions (persist across process() calls) */
	private array $userDefinitions = [];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['image/definition'] = true;
		$texy->addHandler('afterParse', $this->resolveReferences(...));
		$texy->htmlGenerator->registerHandler($this->solve(...));
		$texy->htmlGenerator->registerHandler(fn(ImageDefinitionNode $node) => '');
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

		// [*ref*]: url .(title)[class]{style}
		$this->texy->registerBlockPattern(
			$this->parseDefinition(...),
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
			'image/definition',
		);
	}


	/**
	 * Parses [*image*]: urls .(title)[class]{style}
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseDefinition(BlockParser $parser, array $matches, string $name, array $offsets): ImageDefinitionNode
	{
		[, $mRef, $mURLs, $mMod] = $matches;
		$parsed = $this->parseImageContent($mURLs);
		$modifier = Modifier::parse($mMod);

		return new ImageDefinitionNode(
			trim($mRef),
			$parsed['url'],
			$parsed['width'],
			$parsed['height'],
			$modifier,
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses [* small.jpg 80x13 || big.jpg .(alternative text)[class]{style}>]:LINK
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseImage(InlineParser $parser, array $matches, string $name, array $offsets): ImageNode
	{
		[, $mURLs, $mMod, $mAlign, $mLink] = $matches;
		$parsed = $this->parseImageContent($mURLs);
		$modifier = Modifier::parse($mMod . $mAlign);

		// Determine linked URL
		$linkedUrl = $parsed['linkedUrl'];
		if ($mLink) {
			if ($mLink === ':') { // Use image's linked URL or main URL
				$linkedUrl = $parsed['linkedUrl'] ?? $parsed['url'];
			} else { // Direct URL or reference like [ref] or [*img*]
				$linkedUrl = $mLink;
			}
		}

		return new ImageNode(
			$parsed['url'],
			$parsed['width'],
			$parsed['height'],
			$linkedUrl,
			$modifier,
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parse image content: "image.jpg 100x200 || linked.jpg"
	 * @return array{url: ?string, width: ?int, height: ?int, linkedUrl: ?string}
	 */
	public function parseImageContent(string $content): array
	{
		$parts = explode('|', $content);
		$main = trim($parts[0]);

		$url = $main;
		$width = $height = null;

		// Parse dimensions: "image.jpg 100x200" or "image.jpg 100X200" (asMax)
		if ($m = Regexp::match($main, '~^(.*)\ (\d+|\?)\ *[xX]\ *(\d+|\?)\ *$~U')) {
			$url = trim($m[1]);
			$width = $m[2] === '?' ? null : (int) $m[2];
			$height = $m[3] === '?' ? null : (int) $m[3];
		}

		// Check URL
		if (!$this->texy->checkURL($url, $this->texy::FILTER_IMAGE)) {
			$url = null;
		}

		// Parse linked URL (after ||)
		$linkedUrl = null;
		if (isset($parts[2])) {
			$tmp = trim($parts[2]);
			if ($tmp !== '' && $this->texy->checkURL($tmp, $this->texy::FILTER_ANCHOR)) {
				$linkedUrl = $tmp;
			}
		}

		return [
			'url' => $url,
			'width' => $width,
			'height' => $height,
			'linkedUrl' => $linkedUrl,
		];
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
		$modifier = $alt !== null ? Modifier::parse('(' . $alt . ')') : null;
		$this->userDefinitions[Helpers::toLower($name)] = new ImageDefinitionNode($name, $url, $width, $height, $modifier);
	}


	/**
	 * Resolve image references in the document.
	 * Called via afterParse handler.
	 */
	public function resolveReferences(DocumentNode $doc): void
	{
		// Start with user-defined definitions
		$this->definitions = $this->userDefinitions;
		$traverser = new NodeTraverser;

		// Pass 1: Collect document definitions (overwrites user-defined)
		$traverser->traverse($doc, function (Node $node): ?int {
			if ($node instanceof ImageDefinitionNode) {
				$this->definitions[Helpers::toLower($node->identifier)] = $node;
				return NodeTraverser::DontTraverseChildren;
			}
			return null;
		});

		// Pass 2: Resolve ImageNode and FigureNode.image
		$traverser->traverse($doc, function (Node $node): void {
			if ($node instanceof ImageNode) {
				$this->resolveImageNode($node);
			} elseif ($node instanceof FigureNode) {
				$this->resolveImageNode($node->image);
			}
		});
	}


	private function resolveImageNode(ImageNode $node): void
	{
		if ($node->url === null) {
			return;
		}

		$key = Helpers::toLower(trim($node->url));
		if (!isset($this->definitions[$key])) {
			return;
		}

		$def = $this->definitions[$key];
		$node->url = $def->url;
		$node->width ??= $def->width;
		$node->height ??= $def->height;
		$node->linkedUrl ??= null; // definitions don't have linkedUrl

		// Merge modifier from definition if node doesn't have one
		if ($def->modifier && !$node->modifier) {
			$node->modifier = clone $def->modifier;
		} elseif ($def->modifier && $node->modifier) {
			// Merge: node modifier takes precedence, but inherit missing values from definition
			if ($node->modifier->title === null && $def->modifier->title !== null) {
				$node->modifier->title = $def->modifier->title;
			}
		}
	}


	/**
	 * Get image definition by name (for LinkModule to resolve [*img*] links).
	 */
	public function getDefinition(string $name): ?ImageDefinitionNode
	{
		return $this->definitions[Helpers::toLower($name)] ?? null;
	}


	public function solve(ImageNode $node, Generator $generator): string
	{
		$html = $this->buildImageTag($node, $generator);

		if ($node->linkedUrl) {
			$linkUrl = Helpers::prependRoot($node->linkedUrl, $this->linkedRoot);
			$linkUrl = htmlspecialchars($linkUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$html = "<a href=\"{$linkUrl}\">{$html}</a>";
		}

		return $this->texy->protect($html, $this->texy::CONTENT_REPLACED);
	}


	/**
	 * Build raw <img> tag HTML from ImageNode.
	 */
	public function buildImageTag(ImageNode $node, Generator $generator, bool $includeModifierAttrs = true): string
	{
		$this->detectDimensions($node);

		$attrs = [];

		if ($node->url !== null) {
			$attrs['src'] = Helpers::prependRoot($node->url, $this->root);
		}

		// Alt: modifier.title > modifier.attrs['alt'] > defaultAlt
		$alt = $node->modifier?->title;
		if ($alt !== null) {
			$attrs['alt'] = $this->texy->typographyModule->postLine($alt);
		} elseif (isset($node->modifier?->attrs['alt'])) {
			$attrs['alt'] = $node->modifier->attrs['alt'];
		} else {
			$attrs['alt'] = $this->defaultAlt ?? '';
		}

		if ($node->width !== null) {
			$attrs['width'] = (string) $node->width;
		}
		if ($node->height !== null) {
			$attrs['height'] = (string) $node->height;
		}

		$modAttrs = '';
		if ($includeModifierAttrs && $node->modifier) {
			$mod = clone $node->modifier;
			$mod->title = null;
			unset($mod->attrs['alt']);

			// For images, hAlign means float, not text-align
			if ($mod->hAlign) {
				$class = match ($mod->hAlign) {
					'left' => $this->leftClass,
					'right' => $this->rightClass,
					default => null,
				};
				if ($class) {
					$mod->classes[$class] = true;
				} elseif (!empty($this->texy->alignClasses[$mod->hAlign])) {
					$mod->classes[$this->texy->alignClasses[$mod->hAlign]] = true;
				} else {
					$mod->styles['float'] = $mod->hAlign;
				}
				$mod->hAlign = null;
			}

			$modAttrs = $generator->generateModifierAttrs($mod);
		}

		return '<img' . $generator->generateAttrs($attrs) . $modAttrs . '>';
	}


	/**
	 * Detects image dimensions from file system.
	 */
	private function detectDimensions(ImageNode $node): void
	{
		if ($node->url === null || !Helpers::isRelative($node->url) || str_contains($node->url, '..')) {
			return;
		}

		if ($this->fileRoot === null) {
			return;
		}

		$file = rtrim($this->fileRoot, '/\\') . '/' . $node->url;
		if (!@is_file($file) || !($size = @getimagesize($file))) { // intentionally @
			return;
		}

		if ($node->width === null && $node->height === null) {
			$node->width = $size[0];
			$node->height = $size[1];
		} elseif ($node->width !== null && $node->height === null) {
			$node->height = (int) round($size[1] / $size[0] * $node->width);
		} elseif ($node->height !== null && $node->width === null) {
			$node->width = (int) round($size[0] / $size[1] * $node->height);
		}
	}
}
