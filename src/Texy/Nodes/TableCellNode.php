<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Table cell.
 */
class TableCellNode extends BlockNode
{
	public function __construct(
		public ContentNode $content = new ContentNode,
		public int $colspan = 1,
		public int $rowspan = 1,
		public bool $header = false,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
