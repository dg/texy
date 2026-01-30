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
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use Texy\Syntax;
use function count, strlen;


/**
 * Processes image syntax and detects image dimensions.
 */
final class ImageModule extends Texy\Module
{
	/** @var array<string, ImageDefinitionNode> collected image definitions */
	private array $definitions = [];

	/** @var array<string, ImageDefinitionNode> user-defined definitions (persist across process() calls) */
	private array $userDefinitions = [];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::ImageDefinition] = true;
		$texy->addHandler('afterParse', $this->resolveReferences(...));
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
			Syntax::Image,
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
			Syntax::ImageDefinition,
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
}
