<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Figure.
 * [* image *] *** description
 */
class FigureNode extends BlockNode
{
	public function __construct(
		/** @var array<BlockNode> */
		public array $content,
		public Texy\Image $image,
		public ?Texy\Modifier $modifier = null,
		public ?Texy\Link $link = null,
	) {
		(function (BlockNode ...$content) {})(...$content);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
