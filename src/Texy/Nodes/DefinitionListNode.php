<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Definition list.
 * Term:
 *   - Definition
 */
class DefinitionListNode extends BlockNode
{
	public function __construct(
		/** @var ListItemNode[] */
		public array $items = [],
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
