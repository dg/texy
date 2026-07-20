<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


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
		public ?Range $range = null,
	) {
		(function (TableRowNode ...$rows) {})(...$rows);
	}


	/** @return \Generator<TableRowNode> */
	public function &getChildren(): \Generator
	{
		$gen = $this->yieldList($this->rows);
		return $gen;
	}
}
