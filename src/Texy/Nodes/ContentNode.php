<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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


	/** @return \Generator<InlineNode|BlockNode> */
	public function &getChildren(): \Generator
	{
		$gen = $this->yieldList($this->children);
		return $gen;
	}
}
