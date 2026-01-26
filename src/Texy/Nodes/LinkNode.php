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
		/** @var array<InlineNode> */
		public array $content = [],
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
		/** URL already has root prepended, don't prepend linkModule->root */
		public bool $urlRooted = false,
	) {
		(function (InlineNode ...$content) {})(...$content);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
