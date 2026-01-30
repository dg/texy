<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Position;


/**
 * Root document node.
 */
class DocumentNode extends BlockNode
{
	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Position $position = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
