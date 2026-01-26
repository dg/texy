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
use Texy\Modifier;
use Texy\Nodes\FigureNode;
use Texy\Nodes\ImageNode;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use function htmlspecialchars, strlen, trim;
use const ENT_HTML5, ENT_QUOTES;


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

	/** how calculate div's width */
	public int|false $widthDelta = 10;

	/** caption after *** is required */
	public bool $requireCaption = true;


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
				)' . ($this->requireCaption ? '' : '?') . '
				' . Patterns::MODIFIER_H . '?     # modifier (6)
			$~mU',
			'figure',
		);
	}


	/**
	 * Parses [*image*]:link *** caption .(title)[class]{style}>.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(BlockParser $parser, array $matches, string $name, array $offsets): ?FigureNode
	{
		[, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod] = $matches;

		$texy = $this->texy;

		// Parse image content
		$parsed = $texy->imageModule->parseImageContent($mURLs);
		$modifier = Modifier::parse($mImgMod . $mAlign);

		if ($parsed['url'] === null) {
			return null;
		}

		// Determine link URL
		$linkUrl = null;
		if ($mLink) {
			if ($mLink === ':') {
				// Use image's linked URL or main URL
				$linkUrl = $parsed['linkedUrl'] ?? $parsed['url'];
			} else {
				// Direct URL or reference like [ref] or [*img*]
				$linkUrl = $mLink;
			}
		}

		// Parse caption as inline content
		$caption = [];
		$mContent = trim($mContent ?? '');
		if ($mContent !== '') {
			$captionOffset = $offsets[5] ?? $offsets[0];
			$caption = $texy->createInlineParser()->parse($mContent, $captionOffset);
		}

		return new FigureNode(
			new ImageNode($parsed['url'], $parsed['width'], $parsed['height'], $parsed['linkedUrl'], $modifier),
			$caption,
			Modifier::parse($mMod),
			$linkUrl,
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	public function solve(FigureNode $node, Generator $generator): string
	{
		$imgHtml = $this->texy->imageModule->buildImageTag($node->image, $generator, includeModifierAttrs: false);

		// Wrap in link if present
		if ($node->linkUrl) {
			$linkUrl = Helpers::prependRoot($node->linkUrl, $this->texy->imageModule->linkedRoot);
			$linkUrl = htmlspecialchars($linkUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$imgHtml = "<a href=\"{$linkUrl}\">{$imgHtml}</a>";
		}

		// Build figure attrs with class and alignment
		$mod = $node->modifier ? clone $node->modifier : new Modifier;
		$hAlign = $node->image->modifier?->hAlign;

		$class = $this->class;
		if ($hAlign) {
			$var = $hAlign . 'Class'; // leftClass, rightClass
			if (!empty($this->$var)) {
				$class = $this->$var;
			} elseif (empty($this->texy->alignClasses[$hAlign])) {
				// float style fallback
				$mod->styles['float'] = $hAlign;
			} else {
				$class .= '-' . $this->texy->alignClasses[$hAlign];
			}
		}

		if ($class) {
			$mod->classes[$class] = true;
		}
		$figureAttrs = $generator->generateModifierAttrs($mod);

		$caption = $generator->generateInlineContent($node->caption);
		$captionTag = $this->tagName === 'figure' ? 'figcaption' : 'p';

		$open = $this->texy->protect("<{$this->tagName}{$figureAttrs}>", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("</{$this->tagName}>", $this->texy::CONTENT_BLOCK);
		$imgProtected = $this->texy->protect($imgHtml, $this->texy::CONTENT_REPLACED);

		if ($caption !== '') {
			$captionOpen = $this->texy->protect("<{$captionTag}>", $this->texy::CONTENT_BLOCK);
			$captionClose = $this->texy->protect("</{$captionTag}>", $this->texy::CONTENT_BLOCK);
			return $open . $imgProtected . "\n\t" . $captionOpen . $caption . $captionClose . $close;
		}

		return $open . $imgProtected . $close;
	}
}
