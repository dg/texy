<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\BlockQuoteNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Syntax;
use function max, strlen;


/**
 * Processes blockquote syntax with nested content.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlOutput->registerHandler($this->solve(...));
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
			$~mUx',
			Syntax::Blockquote,
		);
	}


	/**
	 * Parses blockquote.
	 * @param  array<?string>  $matches
	 */
	public function parse(ParseContext $context, array $matches): ?BlockQuoteNode
	{
		/** @var array{string, ?string, string, string} $matches */
		[, $mMod, $mPrefix, $mContent] = $matches;

		// Collect lines
		$lines = [$mContent];
		$spaces = max(1, strlen($mPrefix));

		while ($context->getBlockParser()->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mAx", $matches)) {
			$lines[] = $matches[2] ?? '';
		}

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


	public function solve(BlockQuoteNode $node, Html\Renderer $generator): Html\Element
	{
		$el = new Html\Element('blockquote');
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $generator->renderNodes($node->content->children);
		return $el;
	}
}
