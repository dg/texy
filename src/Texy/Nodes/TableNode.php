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
		public ?Position $position = null,
	) {
		(function (TableRowNode ...$rows) {})(...$rows);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->rows as &$row) {
			yield $row;
		}
		$this->rows = array_values(array_filter($this->rows));
	}
}
