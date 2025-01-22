<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Table.
 * | xxxx | xxxx
 * | xxxx | xxxx
 */
class TableNode extends BlockNode
{
	public function __construct(
		/** @var TableRowNode[] */
		public array $rows = [],
		public ?Texy\Modifier $modifier = null,
	) {
		(function (TableRowNode ...$rows) {})(...$rows);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->rows as &$row) {
			yield $row;
		}
	}
}
