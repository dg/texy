<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Modifier;
use Texy\Position;


/**
 * Figure.
 * [* image *] *** caption
 */
class FigureNode extends BlockNode
{
	public function __construct(
		public ImageNode $image,
		/** @var array<InlineNode> caption content */
		public array $caption = [],
		public ?Modifier $modifier = null,
		public ?string $linkUrl = null,
		public ?Position $position = null,
	) {
		(function (InlineNode ...$caption) {})(...$caption);
	}


	public function &getNodes(): \Generator
	{
		yield $this->image;
		foreach ($this->caption as &$item) {
			yield $item;
		}
	}
}
