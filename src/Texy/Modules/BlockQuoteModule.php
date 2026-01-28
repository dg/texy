<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\BlockQuoteNode;
use Texy\Output\Html;
use Texy\ParseContext;
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
	 */
	public function parse(ParseContext $context, array $matches): ?BlockQuoteNode
	{
		[, $mMod, $mPrefix, $mContent] = $matches;

		// Collect lines
		$lines = [$mContent ?? ''];
		$spaces = '';

		do {
			if ($spaces === '') {
				$spaces = max(1, strlen($mPrefix));
			}

			if (!$context->getBlockParser()->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mA", $matches)) {
				break;
			}

			[, $mPrefix, $mContent] = $matches;
			$lines[] = $mContent ?? '';
		} while (true);

		// Join content for parsing
		$content = implode("\n", $lines);

		// Parse nested content
		$parsed = $context->parseBlock(trim($content));
		if (!$parsed->children) {
			return null;
		}

		return new BlockQuoteNode(
			$parsed,
			Texy\Modifier::parse($mMod),
		);
	}


	public function solve(BlockQuoteNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element('blockquote');
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $generator->renderNodes($node->content->children);
		return $el;
	}
}
