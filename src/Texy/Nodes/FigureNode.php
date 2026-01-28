<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Modifier;


/**
 * Figure.
 * [* image *] *** caption
 */
class FigureNode extends BlockNode
{
	public function __construct(
		/** Image node, or LinkNode wrapping the image when figure has a link */
		public ImageNode|LinkNode $image,
		public ?ContentNode $caption = null,
		public ?Modifier $modifier = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->image;
		if ($this->caption !== null) {
			yield $this->caption;
		}
	}
}
