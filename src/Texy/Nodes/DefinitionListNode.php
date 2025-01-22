<?php

/**
 * This file is part of the Texy! (https://texy.info)
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
		/** @var DefinitionItemNode[] */
		public array $items = [],
		public ?Texy\Modifier $modifier = null,
	) {
		(function (DefinitionItemNode ...$items) {})(...$items);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->items as &$item) {
			yield $item;
		}
	}
}
