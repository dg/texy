<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\{HasModifier, Modifier, Range};


/**
 * Figure.
 * [* image *] *** caption
 */
class FigureNode extends BlockNode implements HasModifier
{
	public function __construct(
		/** Image node, or LinkNode wrapping the image when figure has a link */
		public ImageNode|LinkNode $image,
		public ?ContentNode $caption = null,
		public ?Modifier $modifier = null,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->image;
		if ($this->caption !== null) {
			yield $this->caption;
		}
	}


	public function getModifier(): ?Modifier
	{
		return $this->modifier;
	}
}
