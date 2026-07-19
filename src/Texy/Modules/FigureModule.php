<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\FigureNode;
use Texy\Nodes\ImageNode;
use Texy\Nodes\LinkNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Range;
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
					([^\n]{1,1000})               # URLs (1)
					' . Patterns::Modifier . '?   # modifier (2)
					\ *+
					( \* | (?<! < ) > | < )       # alignment (3)
				]
				)
				(?:
					:(' . Patterns::LinkUrl . ' | : ) # link or colon (4)
				)??
				(?:
					\ ++ \*\*\* \ ++              # separator
					(.{0,2000})                   # caption (5)
				)?
				' . Patterns::ModifierHAlign . '?     # modifier (6)
			$~mUx',
			Syntax::Figure,
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

		$texy = $this->texy;

		// Parse image content
		$parsed = $texy->imageModule->parseImageContent($mURLs);
		$modifier = Modifier::parse($mImgMod . $mAlign);

		if ($parsed['url'] === null) {
			return null;
		}

		// Create ImageNode; its source span is "[* ... <alignment>]"
		$imageEnd = $offsets[3] + strlen($mAlign) + 1; // 1 = "]"
		$imageNode = new ImageNode(
			$parsed['url'],
			$parsed['width'],
			$parsed['height'],
			$modifier,
			new Range($offsets[0], $imageEnd - $offsets[0]),
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
				range: new Range($offsets[0], $offsets[4] + strlen($mLink) - $offsets[0]),
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
			Modifier::parse($mMod, $offsets[6] ?? null),
			new Range($offsets[0], strlen($matches[0])),
		);
	}
}
