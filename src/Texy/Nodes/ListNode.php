<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * List.
 * - Item
 * - Item
 */
class ListNode extends BlockNode
{
	public function __construct(
		/** @var ListItemNode[] */
		public array $items = [],
		public ?Texy\Modifier $modifier = null,
	) {
		(function (ListItemNode ...$items) {})(...$items);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->items as &$item) {
			yield $item;
		}
	}
}
