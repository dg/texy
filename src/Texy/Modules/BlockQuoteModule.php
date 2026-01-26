<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Position;
use function max, strlen;


/**
 * Processes blockquote syntax with nested content.
 */
final class BlockQuoteModule extends Texy\Module
{
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
				(?: ' . Texy\Patterns::MODIFIER_H . '\n)? # modifier (1)
				>                                      # blockquote char
				( [ \t]++ | : )                        # space/tab or colon (2)
				( \S.*+ )                              # content (3)
			$~mU',
			'blockquote',
		);
	}


	/**
	 * Parses blockquote.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(
		Texy\BlockParser $parser,
		array $matches,
		string $name,
		array $offsets,
	): ?Texy\Nodes\BlockQuoteNode
	{
		[, $mMod, $mPrefix, $mContent] = $matches;

		$startOffset = $offsets[0];
		$totalLength = strlen($matches[0]);

		$content = '';
		$spaces = '';
		do {
			if ($spaces === '') {
				$spaces = max(1, strlen($mPrefix));
			}
			$content .= $mContent . "\n";

			if (!$parser->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mA", $matches)) {
				break;
			}

			$totalLength += strlen($matches[0]) + 1; // +1 for \n
			[, $mPrefix, $mContent] = $matches;
		} while (true);

		// Parse nested content
		$parsed = $this->texy->createBlockParser()->parse(trim($content));
		if (!$parsed) {
			return null;
		}

		return new Texy\Nodes\BlockQuoteNode(
			$parsed,
			Texy\Modifier::parse($mMod),
			new Position($startOffset, $totalLength),
		);
	}


	public function solve(Nodes\BlockQuoteNode $node, Generator $generator): string
	{
		$content = $generator->generateBlockContent($node->content);
		$attrs = $generator->generateModifierAttrs($node->modifier);
		$open = $this->texy->protect("<blockquote{$attrs}>\n", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("\n</blockquote>", $this->texy::CONTENT_BLOCK);
		return $open . $content . $close;
	}
}
