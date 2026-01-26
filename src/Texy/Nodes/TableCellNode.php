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
 * Table cell.
 */
class TableCellNode extends BlockNode
{
	public function __construct(
		/** @var array<InlineNode|BlockNode> */
		public array $content = [],
		public int $colspan = 1,
		public int $rowspan = 1,
		public bool $isHeader = false,
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
	) {
		(function (InlineNode|BlockNode ...$content) {})(...$content);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
