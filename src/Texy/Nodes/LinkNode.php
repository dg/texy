<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Link.
 * "Link text":https://example.com
 * [Link text](https://example.com)
 */
class LinkNode extends InlineNode
{
	public function __construct(
		/** @var array<InlineNode> */
		public array $content = [],
		public ?Texy\Modifier $modifier = null,
	) {
		(function (InlineNode ...$content) {})(...$content);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
