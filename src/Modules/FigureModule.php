<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Modifier, Patterns, Range, Syntax};
use Texy\Nodes\{ContentNode, FigureNode, ImageNode, LinkNode};
use Texy\Parsing\ParseContext;
use function in_array, is_array, strlen;


/**
 * Processes images with captions.
 */
final class FigureModule extends Texy\Module
{
	public function __construct(
		private ImageModule $imageModule,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->addBlock(
			Syntax::Figure,
			'~^
				(?>
					\[\* \ *+                     # opening bracket with asterisk
					([^\n]{1,1000})               # URLs (1)
					' . Patterns::Modifier . '?   # modifier (2)
					\ *+
					( \* | (?<! < ) > | < )       # alignment (3)
				]
				)
				(?:
					:(' . Patterns::LinkUrl . ' | : ) # link or just colon (4)
				)??
				(?:
					\ ++ \*\*\* \ ++              # separator
					(.{0,2000})                   # caption (5)
				)?
				' . Patterns::ModifierHAlign . '?     # modifier (6)
			$~mUx',
			$this->parse(...),
		);
	}


	/**
	 * Parses [*image*]:link *** caption .(title)[class]{style}>.
	 * @param  array{string, string, ?string, string, ?string, ?string, ?string}  $matches
	 * @param  array{int, int, ?int, int, ?int, ?int, ?int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?FigureNode
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;

		$parsed = $this->imageModule->parseImageContent($mURLs, $offsets[1]);
		if ($parsed['url'] === null) {
			return null;
		}

		// the float is written in the brackets of the image, but it is the figure that floats
		$modifier = Modifier::parse($mImgMod . $mAlign);
		$float = $modifier !== null && in_array($modifier->hAlign, ['left', 'right'], true) ? $modifier->hAlign : null;
		if ($float !== null) {
			$modifier->hAlign = null;
		}

		// the alternative text is written as .(text); {alt: text} wins
		$alt = null;
		if ($modifier !== null) {
			$alt = $modifier->attrs['alt'] ?? $modifier->title;
			unset($modifier->attrs['alt']);
			$modifier->title = null;
		}

		// ImageNode source span is "[* ... <alignment>]"
		$imageNode = new ImageNode(
			$parsed['url'],
			is_array($alt) ? implode(' ', $alt) : $alt,
			$parsed['width'],
			$parsed['height'],
			modifier: $modifier,
			range: new Range($offsets[0], $offsets[3] + strlen($mAlign) + 1 - $offsets[0]), // 1 = "]"
			urlRange: $parsed['urlRange'],
		);

		// ':' links to the image itself; a reference like [ref] or [*img*] is recognized by LinkReferenceModule
		$image = $imageNode;
		if ($mLink) {
			$linkOffset = $offsets[4] ?? throw new \LogicException('Link without group 4.');
			$isSelf = $mLink === ':';
			$image = new LinkNode(
				url: $isSelf ? $imageNode->url : Helpers::decodeEntities($mLink),
				content: new ContentNode([$imageNode]),
				range: new Range($offsets[0], $linkOffset + strlen($mLink) - $offsets[0]),
				isImageLink: $isSelf,
				syntax: Syntax::Figure,
				urlRange: new Range($linkOffset, strlen($mLink)),
			);
		}

		// Parse caption as inline content
		$caption = null;
		$mContent = trim($mContent ?? '');
		if ($mContent !== '') {
			$caption = $context->parseInline($mContent, $offsets[5] ?? $offsets[0]);
		}

		return new FigureNode(
			$image,
			$caption,
			$float,
			Modifier::parse($mMod, $offsets[6] ?? null),
			new Range($offsets[0], strlen($matches[0])),
		);
	}
}
