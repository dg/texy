<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;
use Texy\Position;


/**
 * Link.
 * "Link text":https://example.com
 * [Link text](https://example.com)
 */
class LinkNode extends InlineNode
{
	public function __construct(
		public ?string $url = null,
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
		/** Link targets an image URL (use imageModule.root instead of linkModule.root) */
		public bool $isImageLink = false,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
