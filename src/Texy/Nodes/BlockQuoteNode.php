<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * Blockquote.
 * > Blockquote text
 */
class BlockQuoteNode extends BlockNode
{
	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
