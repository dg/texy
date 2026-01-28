<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
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
		public ListType $type = ListType::Unordered,
		public ?int $start = null,
		public ?Texy\Modifier $modifier = null,
	) {
		(function (ListItemNode ...$items) {})(...$items);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->items as &$item) {
			yield $item;
		}
		$this->items = array_values(array_filter($this->items));
	}
}
