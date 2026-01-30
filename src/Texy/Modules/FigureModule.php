<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\FigureNode;
use Texy\Nodes\ImageNode;
use Texy\Nodes\LinkNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Position;
use Texy\Syntax;
use function strlen;


/**
 * Processes images with captions.
 */
final class FigureModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				(?>
					\[\*\ *+                      # opening bracket with asterisk
					([^\n' . Patterns::MARK . ']{1,1000}) # URLs (1)
					' . Patterns::MODIFIER . '?   # modifier (2)
					\ *+
					( \* | (?<! < ) > | < )       # alignment (3)
				]
				)
				(?:
					:(' . Patterns::LINK_URL . ' | : ) # link or colon (4)
				)??
				(?:
					\ ++ \*\*\* \ ++              # separator
					(.{0,2000})                   # caption (5)
				)?
				' . Patterns::MODIFIER_H . '?     # modifier (6)
			$~mU',
			Syntax::Figure,
		);
	}


	/**
	 * Parses [*image*]:link *** caption .(title)[class]{style}>.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?FigureNode
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;

		$texy = $this->texy;

		// Parse image content
		$parsed = $texy->imageModule->parseImageContent($mURLs);
		$modifier = Modifier::parse($mImgMod . $mAlign);

		if ($parsed['url'] === null) {
			return null;
		}

		// Create ImageNode
		$imageNode = new ImageNode(
			$parsed['url'],
			$parsed['width'],
			$parsed['height'],
			$modifier,
		);

		// If figure has link, wrap image in LinkNode
		$image = $imageNode;
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

			$image = new LinkNode(
				url: $linkUrl,
				content: new ContentNode([$imageNode]),
				isImageLink: $isImageLink,
			);
		}

		// Parse caption as inline content
		$caption = null;
		$mContent = trim($mContent ?? '');
		if ($mContent !== '') {
			$captionOffset = $offsets[5] ?? $offsets[0];
			$caption = $context->parseInline($mContent, $captionOffset);
		}

		return new FigureNode(
			$image,
			$caption,
			Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}
}
