<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Container for node content (inline or block children).
 */
class ContentNode extends Texy\Node
{
	/**
	 * @param array<InlineNode|BlockNode> $children
	 */
	public function __construct(
		public array $children = [],
	) {
	}


	/**
	 * Yields children by reference. Children can be set to null during iteration.
	 * After iteration, null children are filtered out and array is re-indexed.
	 * @return \Generator<InlineNode|BlockNode|null>
	 */
	public function &getNodes(): \Generator
	{
		foreach ($this->children as &$item) {
			yield $item;
		}
		$this->children = array_values(array_filter($this->children));
	}
}
