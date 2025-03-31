<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Modifier, Patterns, Syntax};
use Texy\Nodes\{ContentNode, FigureNode, ImageNode, LinkNode};
use Texy\Parsing\ParseContext;
use function in_array, is_array;


/**
 * Processes images with captions.
 */
final class FigureModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(): void
	{
		$this->texy->addBlock(
			Syntax::Figure,
			'~^
				(?>
					\[\* \ *+                     # opening bracket with asterisk
					([^\n' . Patterns::MARK . ']{1,1000}) # URLs (1)
					' . Patterns::MODIFIER . '?   # modifier (2)
					\ *+
					( \* | (?<! < ) > | < )       # alignment (3)
				]
				)
				(?:
					:(' . Patterns::LINK_URL . ' | : ) # link or just colon (4)
				)??
				(?:
					\ ++ \*\*\* \ ++              # separator
					(.{0,2000})                   # caption (5)
				)?
				' . Patterns::MODIFIER_H . '?     # modifier (6)
			$~mUx',
			$this->parse(...),
		);
	}


	/**
	 * Parses [*image*]:link *** caption .(title)[class]{style}>.
	 * @param  array{string, string, ?string, string, ?string, ?string, ?string}  $matches
	 */
	public function parse(ParseContext $context, array $matches): FigureNode
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;

		$parsed = $this->texy->imageModule->parseImageContent($mURLs);

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

		$imageNode = new ImageNode(
			$parsed['url'],
			is_array($alt) ? implode(' ', $alt) : $alt,
			$parsed['width'],
			$parsed['height'],
			modifier: $modifier,
		);

		// ':' links to the image itself; a reference like [ref] or [*img*] is recognized by LinkReferenceModule
		$image = $imageNode;
		if ($mLink) {
			$isSelf = $mLink === ':';
			$image = new LinkNode(
				url: $isSelf ? $imageNode->url : Helpers::decodeEntities($mLink),
				content: new ContentNode([$imageNode]),
				isImageLink: $isSelf,
			);
		}

		// Parse caption as inline content
		$caption = null;
		$mContent = trim($mContent ?? '');
		if ($mContent !== '') {
			$caption = $context->parseInline($mContent);
		}

		return new FigureNode(
			$image,
			$caption,
			$float,
			Modifier::parse($mMod),
		);
	}
}
