<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\{HasModifier, Range};


/**
 * Table row.
 */
class TableRowNode extends BlockNode implements HasModifier
{
	public function __construct(
		/** @var TableCellNode[] */
		public array $cells = [],
		public bool $header = false,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
	) {
		(function (TableCellNode ...$cells) {})(...$cells);
	}


	/** @return \Generator<TableCellNode> */
	public function &getChildren(): \Generator
	{
		foreach (self::walk($this->cells, fn(TableCellNode ...$cells) => null) as &$cell) {
			yield $cell;
		}
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
