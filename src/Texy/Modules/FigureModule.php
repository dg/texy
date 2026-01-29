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
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Syntax;
use function strlen;


/**
 * Processes images with captions.
 */
final class FigureModule extends Texy\Module
{
	public string $tagName = 'div';

	/** non-floated box CSS class */
	public ?string $class = 'figure';

	/** left-floated box CSS class */
	public ?string $leftClass = null;

	/** right-floated box CSS class */
	public ?string $rightClass = null;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solve(...));
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
	 */
	public function parse(ParseContext $context, array $matches): ?FigureNode
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
			$caption = $context->parseInline($mContent);
		}

		return new FigureNode(
			$image,
			$caption,
			Modifier::parse($mMod),
		);
	}


	public function solve(FigureNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element($this->tagName);

		// Get the actual ImageNode (might be wrapped in LinkNode)
		$image = $node->image;
		$imageNode = $image instanceof LinkNode && isset($image->content->children[0]) && $image->content->children[0] instanceof ImageNode
			? $image->content->children[0]
			: $image;

		// Extract alignment from ImageNode - we'll apply it to the figure wrapper instead
		$hAlign = $imageNode instanceof ImageNode ? $imageNode->modifier?->hAlign : null;
		if ($imageNode instanceof ImageNode && $imageNode->modifier) {
			$imageNode->modifier->hAlign = null; // Clear so ImageModule won't add it to <img>
		}

		// Build image via generator - this allows custom ImageNode handlers to work
		$el->children = $generator->renderNodes([$image]);

		// Caption
		if ($node->caption !== null) {
			$el->create($this->tagName === 'figure' ? 'figcaption' : 'p')
				->children = $generator->renderNodes($node->caption->children);
		}

		// Modifier classes/styles
		$node->modifier?->decorate($this->texy, $el);

		// Figure class based on alignment (extracted from ImageNode above)
		$class = $this->class;
		if ($hAlign) {
			$var = $hAlign . 'Class';
			if (!empty($this->$var)) {
				$class = $this->$var;
			} elseif (empty($this->texy->alignClasses[$hAlign])) {
				$el->attrs['style'] = (array) ($el->attrs['style'] ?? []);
				$el->attrs['style']['float'] = $hAlign;
			} else {
				$class .= '-' . $this->texy->alignClasses[$hAlign];
			}
		}

		if ($class) {
			$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
			$el->attrs['class'][] = $class;
		}

		return $el;
	}
}
