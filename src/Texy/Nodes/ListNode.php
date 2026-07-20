<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


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
		public ?Range $range = null,
	) {
		(function (ListItemNode ...$items) {})(...$items);
	}


	/** @return \Generator<ListItemNode> */
	public function &getChildren(): \Generator
	{
		$gen = $this->yieldList($this->items);
		return $gen;
	}
}
