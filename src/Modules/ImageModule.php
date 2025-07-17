<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Modifier, Node, NodeTraverser, Patterns, Range, Regexp, Syntax, TraverseAction};
use Texy\Nodes\{ContentNode, DocumentNode, ImageDefinitionNode, ImageNode, LinkNode};
use Texy\Parsing\ParseContext;
use function count, in_array, is_array, strlen;


/**
 * Processes image syntax.
 */
final class ImageModule extends Texy\Module
{
	/** @var array<string, ImageDefinitionNode> collected image definitions */
	private array $definitions = [];


	public function __construct(
		private Texy\TexyConfig $config,
		private Texy\UrlPolicy $urlPolicy,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		// [*image*]:LINK
		$registry->addInline(
			Syntax::Image,
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
			~Ux',
			$this->parseImage(...),
		);

		// [*ref*]: url .(title)[class]{style}
		$registry->addBlock(
			Syntax::ImageDefinition,
			'~^
				\[\*                              # opening [*
				( [^\n]{1,100} )                  # reference (1)
				\*]                               # closing *]
				: [ \t]+
				(.{1,1000})                       # URL (2)
				[ \t]*
				' . Patterns::MODIFIER . '?       # modifier (3)
				\s*
			$~mUx',
			$this->parseDefinition(...),
		);
	}


	/**
	 * Parses [*image*]: urls .(title)[class]{style}
	 * @param  array{string, string, string, ?string}  $matches
	 * @param  array{int, int, int, ?int}  $offsets
	 */
	public function parseDefinition(ParseContext $context, array $matches, array $offsets): ImageDefinitionNode
	{
		[, $mRef, $mURLs, $mMod] = $matches;
		$parsed = $this->parseImageContent($mURLs);
		$modifier = Modifier::parse($mMod, $offsets[3] ?? null);

		return new ImageDefinitionNode(
			trim($mRef),
			$parsed['url'],
			self::takeAlt($modifier),
			$parsed['width'],
			$parsed['height'],
			$modifier,
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses [* small.jpg 80x13 .(alternative text)[class]{style}>]:LINK
	 * @param  array{string, string, ?string, string, ?string}  $matches
	 * @param  array{int, int, ?int, int, ?int}  $offsets
	 */
	public function parseImage(ParseContext $context, array $matches, array $offsets): ImageNode|LinkNode|null
	{
		[, $mURLs, $mMod, $mAlign, $mLink] = $matches;
		$imageNode = $this->createImage($mURLs, $mMod . $mAlign, $offsets);
		if ($imageNode === null) {
			return null;
		}

		return $mLink
			? $this->wrapInLink(
				$imageNode,
				$mLink,
				$offsets[0],
				$offsets[4] ?? throw new \LogicException('Link without group 4.'),
				Syntax::Image,
			)
			: $imageNode;
	}


	/**
	 * Creates the image written as [* $mURLs $mMod *]; the source span is "[* ... <alignment>]".
	 * @param  array<?int>  $offsets
	 */
	private function createImage(string $mURLs, string $mMod, array $offsets): ?ImageNode
	{
		$parsed = $this->parseImageContent($mURLs, $offsets[1]);
		if ($parsed['url'] === null) {
			return null;
		}

		$modifier = Modifier::parse($mMod);
		$float = $modifier !== null && in_array($modifier->hAlign, ['left', 'right'], true) ? $modifier->hAlign : null;
		if ($float !== null) {
			$modifier->hAlign = null;
		}

		$start = $offsets[0] ?? throw new \LogicException('Match without group 0.');
		$alignEnd = ($offsets[3] ?? throw new \LogicException('Match without group 3.')) + 1; // the alignment is one character
		return new ImageNode(
			$parsed['url'],
			self::takeAlt($modifier),
			$parsed['width'],
			$parsed['height'],
			$float,
			$modifier,
			new Range($start, $alignEnd + 1 - $start), // 1 = "]"
			urlRange: $parsed['urlRange'],
		);
	}


	/**
	 * Takes the alternative text out of the modifier, which writes it as .(text); {alt: text} wins.
	 */
	private static function takeAlt(?Modifier $modifier): ?string
	{
		if ($modifier === null) {
			return null;
		}

		$alt = $modifier->attrs['alt'] ?? $modifier->title;
		unset($modifier->attrs['alt']);
		$modifier->title = null;
		return is_array($alt) ? implode(' ', $alt) : $alt;
	}


	/** Wraps the image written at $offset in the link written after it at $linkOffset. */
	private function wrapInLink(ImageNode $imageNode, string $mLink, int $offset, int $linkOffset, string $syntax): LinkNode
	{
		// ':' links to the image itself; a reference like [ref] or [*img*] is recognized by LinkReferenceModule
		$isSelf = $mLink === ':';
		return new LinkNode(
			url: $isSelf ? $imageNode->url : Helpers::decodeEntities($mLink),
			content: new ContentNode([$imageNode]),
			range: new Range($offset, $linkOffset + strlen($mLink) - $offset),
			isImageLink: $isSelf,
			syntax: $syntax,
			urlRange: new Range($linkOffset, strlen($mLink)),
		);
	}


	/**
	 * Parse image content: "image.jpg 100x200"; $offset is where the content is written.
	 * @return array{url: ?string, width: ?int, height: ?int, urlRange: ?Range}
	 */
	public function parseImageContent(string $content, ?int $offset = null): array
	{
		$parts = explode('|', $content);
		$main = $written = trim($parts[0]);

		if (count($parts) > 1) {
			trigger_error(
				"Image syntax with '|' or '||' inside brackets is deprecated. Use [* image *]:url for linked images.",
				E_USER_DEPRECATED,
			);
		}

		$url = Helpers::decodeEntities($main);
		$width = $height = null;

		// Parse dimensions: "image.jpg 100x200" or "image.jpg 100X200" (asMax)
		if ($m = Regexp::match($main, '~^(.*)\ (\d+|\?)\ *[xX]\ *(\d+|\?)\ *$~U')) {
			/** @var array{string, string, string, string} $m */
			$url = $written = trim($m[1]);
			$width = $m[2] === '?' ? null : (int) $m[2];
			$height = $m[3] === '?' ? null : (int) $m[3];
		}

		// Check URL
		if (!$this->urlPolicy->isImageAllowed($url)) {
			$url = null;
		}

		return [
			'url' => $url,
			'width' => $width,
			'height' => $height,
			'urlRange' => $offset === null ? null : new Range($offset + strlen($parts[0]) - strlen(ltrim($parts[0])), strlen($written)),
		];
	}


	/**
	 * Resolve image references in the document.
	 */
	public function resolveReferences(DocumentNode $doc): void
	{
		// Start with the definitions of the application
		$this->definitions = $this->config->imageDefinitions;

		// Pass 1: Collect document definitions (overwrites user-defined), one with a rejected URL defines nothing
		foreach ($doc->find(ImageDefinitionNode::class, fn($node) => $node->url !== null) as $node) {
			$this->definitions[Helpers::toLower($node->reference)] = $node;
		}

		// Pass 2: Resolve ImageNode references (NodeTraverser visits all nodes including FigureNode.image)
		(new NodeTraverser)->traverse($doc, function (Node $node): ?TraverseAction {
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

		$node->ref = trim($node->url); // keep the written reference name for analyzers
		$node->url = $def->url;
		$node->alt ??= $def->alt;
		$node->width ??= $def->width;
		$node->height ??= $def->height;

		// Use modifier from definition if node doesn't have one
		if ($def->modifier && !$node->modifier) {
			$node->modifier = clone $def->modifier;
		}
	}


	/**
	 * Get image definition by name (for LinkReferenceModule to resolve [*img*] links).
	 */
	public function getDefinition(string $name): ?ImageDefinitionNode
	{
		return $this->definitions[Helpers::toLower($name)] ?? null;
	}
}
