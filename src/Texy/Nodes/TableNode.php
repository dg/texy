<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\{HasModifier, Range};


/**
 * Table.
 * | xxxx | xxxx
 * | xxxx | xxxx
 */
class TableNode extends BlockNode implements HasModifier
{
	public function __construct(
		/** @var TableRowNode[] */
		public array $rows = [],
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
		/** caption written as |## Text above the first row */
		public ?ContentNode $caption = null,
	) {
		(function (TableRowNode ...$rows) {})(...$rows);
	}


	/** @return \Generator<TableRowNode|ContentNode> */
	public function &getChildren(): \Generator
	{
		if ($this->caption !== null) {
			yield $this->caption;
		}

		foreach (self::walk($this->rows, fn(TableRowNode ...$rows) => null) as &$row) {
			yield $row;
		}
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
