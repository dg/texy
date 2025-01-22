<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\BlockQuoteNode;


/**
 * Blockquote module.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler(BlockQuoteNode::class, $this->toElement(...));
		$texy->registerBlockPattern(
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
	 * Callback for:.
	 *
	 * > They went in single file, running like hounds on a strong scent,
	 * and an eager light was in their eyes. Nearly due west the broad
	 * swath of the marching Orcs tramped its ugly slot; the sweet grass
	 * of Rohan had been bruised and blackened as they passed.
	 * >:http://www.mycom.com/tolkien/twotowers.html
	 */
	public function parse(Texy\BlockParser $parser, array $matches): BlockQuoteNode
	{
		[, $mMod, $mPrefix, $mContent] = $matches;
		// [1] => .(title)[class]{style}<>
		// [2] => spaces |
		// [3] => ... / LINK

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

			[, $mPrefix, $mContent] = $matches;
		} while (true);

		return new BlockQuoteNode(
			$parser->getTexy()->parseBlock($content, $parser->isIndented()),
			$mMod ? new Texy\Modifier($mMod) : null,
		);
	}


	public function toElement(BlockQuoteNode $node, Texy\Texy $texy): ?Texy\HtmlElement
	{
		$el = new Texy\HtmlElement('blockquote');
		$el->inject($texy, $node->content, $node->modifier);
		return $el->getChildren() ? $el : null;
	}
}
