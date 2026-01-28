<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Blockquote.
 * > Blockquote text
 */
class BlockQuoteNode extends BlockNode
{
	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
