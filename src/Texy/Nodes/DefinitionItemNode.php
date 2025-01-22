<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Definition list item.
 */
class DefinitionItemNode extends BlockNode
{
	public function __construct(
		/** @var array<InlineNode|BlockNode> */
		public array $content = [],
		public bool $term = false,
		public ?Texy\Modifier $modifier = null,
	) {
		(function (InlineNode|BlockNode ...$content) {})(...$content);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
