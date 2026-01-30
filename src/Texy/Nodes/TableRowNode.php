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
 * Table row.
 */
class TableRowNode extends BlockNode
{
	public function __construct(
		/** @var TableCellNode[] */
		public array $cells = [],
		public bool $header = false,
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
	) {
		(function (TableCellNode ...$cells) {})(...$cells);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->cells as &$cell) {
			yield $cell;
		}
		$this->cells = array_values(array_filter($this->cells));
	}
}
