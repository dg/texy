<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


/**
 * Table row.
 */
class TableRowNode extends BlockNode
{
	public function __construct(
		/** @var TableCellNode[] */
		public array $cells = [],
	) {
		(function (TableCellNode ...$cells) {})(...$cells);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->cells as &$cell) {
			yield $cell;
		}
	}
}
