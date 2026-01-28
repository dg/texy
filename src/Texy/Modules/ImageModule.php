<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Modifier;
use Texy\Node;
use Texy\Nodes\ContentNode;
use Texy\Nodes\DocumentNode;
use Texy\Nodes\ImageDefinitionNode;
use Texy\Nodes\ImageNode;
use Texy\Nodes\LinkNode;
use Texy\NodeTraverser;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use function count, strlen;


/**
 * Processes image syntax and detects image dimensions.
 */
final class ImageModule extends Texy\Module
{
	/** root of relative images (http) */
	public ?string $root = 'images/';

	/** physical location of images on server */
	public ?string $fileRoot = null;

	/** left-floated images CSS class */
	public ?string $leftClass = null;

	/** right-floated images CSS class */
	public ?string $rightClass = null;

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
	 */
	public function parseDefinition(ParseContext $context, array $matches): ImageDefinitionNode
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
		);
	}


	/**
	 * Parses [* small.jpg 80x13 .(alternative text)[class]{style}>]:LINK
	 * @param  array<?string>  $matches
	 */
	public function parseImage(ParseContext $context, array $matches): ImageNode|LinkNode
	{
		[, $mURLs, $mMod, $mAlign, $mLink] = $matches;
		$parsed = $this->parseImageContent($mURLs);
		$modifier = Modifier::parse($mMod . $mAlign);

		$imageNode = new ImageNode(
			$parsed['url'],
			$parsed['width'],
			$parsed['height'],
			$modifier,
		);

		// If image has link, wrap in LinkNode
		if ($mLink) {
			if ($mLink === ':') {
				// Link to image itself - use imageModule.root
				$linkUrl = $parsed['linkedUrl'] ?? $parsed['url'];
				$isImageLink = true;
			} else {
				// Direct URL or reference like [ref] or [*img*]
				$linkUrl = $mLink;
				// Check if it's an image reference [*...*] → use imageModule.root
				$len = strlen($mLink);
				$isImageLink = $len > 4 && $mLink[0] === '[' && $mLink[1] === '*'
					&& $mLink[$len - 1] === ']' && $mLink[$len - 2] === '*';
			}

			return new LinkNode(
				url: $linkUrl,
				content: new ContentNode([$imageNode]),
				isImageLink: $isImageLink,
			);
		}

		return $imageNode;
	}


	/**
	 * Parse image content: "image.jpg 100x200"
	 * @return array{url: ?string, width: ?int, height: ?int, linkedUrl: ?string}
	 */
	public function parseImageContent(string $content): array
	{
		$parts = explode('|', $content);
		$main = trim($parts[0]);

		if (count($parts) > 1) {
			trigger_error("Image syntax with '|' or '||' inside brackets is deprecated. Use [* image *]:url for linked images.", E_USER_DEPRECATED);
		}

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

		return [
			'url' => $url,
			'width' => $width,
			'height' => $height,
			'linkedUrl' => null,
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

		// Pass 2: Resolve ImageNode references (NodeTraverser visits all nodes including FigureNode.image)
		$traverser->traverse($doc, function (Node $node): ?int {
			if ($node instanceof ImageNode) {
				$this->resolveImageNode($node);
			} elseif (
				$node instanceof LinkNode
				&& ($imageNode = $node->content->children[0] ?? null) instanceof ImageNode
			) {
				// LinkNode wrapping ImageNode - resolve image and possibly the link URL
				$imageKey = $imageNode->url !== null ? Helpers::toLower(trim($imageNode->url)) : null;
				$linkKey = $node->url !== null ? Helpers::toLower(trim($node->url)) : null;

				// Resolve the image
				$this->resolveImageNode($imageNode);

				// If link URL matches the original image reference (from :: syntax), resolve it too
				if ($imageKey !== null && $linkKey === $imageKey && isset($this->definitions[$imageKey])) {
					$node->url = $this->definitions[$imageKey]->url;
				}
			}
			return null;
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


	public function solve(ImageNode $node, Html\Generator $generator): Html\Element
	{
		$this->detectDimensions($node);

		$el = new Html\Element('img');
		$mod = $node->modifier;

		// Extract and clear title/hAlign before decorate
		$alt = $mod?->title;
		$hAlign = $mod?->hAlign;
		if ($mod) {
			$mod->title = null;
			$mod->hAlign = null;
		}

		// Custom attrs from modifier (like {alt:...; title:...})
		$hasCustomAlt = isset($mod?->attrs['alt']);
		if ($hasCustomAlt) {
			$el->attrs['alt'] = $mod->attrs['alt'];
		}
		if (isset($mod?->attrs['title'])) {
			$el->attrs['title'] = $mod->attrs['title'];
		}

		// Reserve src position (decorate() may overwrite attrs array)
		$el->attrs['src'] = null;

		// class/style from modifier
		$mod?->decorate($this->texy, $el);

		// src
		$el->attrs['src'] = $node->url !== null ? Helpers::prependRoot($node->url, $this->root) : null;

		// alt: from title or empty (if not set by custom attrs)
		if (!$hasCustomAlt && !isset($el->attrs['alt'])) {
			$el->attrs['alt'] = $alt !== null
				? $this->texy->typographyModule->postLine($alt)
				: '';
		}

		// hAlign → float class or style
		if ($hAlign) {
			$class = match ($hAlign) {
				'left' => $this->leftClass,
				'right' => $this->rightClass,
				default => null,
			};
			if ($class) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $class;
			} elseif (!empty($this->texy->alignClasses[$hAlign])) {
				$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
				$el->attrs['class'][] = $this->texy->alignClasses[$hAlign];
			} else {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			}
		}

		// dimensions
		$el->attrs['width'] = $node->width;
		$el->attrs['height'] = $node->height;

		return $el;
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
