<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;


/**
 * Table row.
 */
class TableRowNode extends BlockNode
{
	public function __construct(
		/** @var TableCellNode[] */
		public array $cells = [],
		public bool $header = false,
		public ?Texy\Modifier $modifier = null,
	) {
		(function (TableCellNode ...$cells) {})(...$cells);
	}


	/** @return \Generator<TableCellNode> */
	public function &getChildren(): \Generator
	{
		$gen = $this->yieldList($this->cells);
		return $gen;
	}
}
