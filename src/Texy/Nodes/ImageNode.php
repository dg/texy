<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\{HasModifier, Modifier, Range};


/**
 * Image.
 * [* image *]
 */
class ImageNode extends InlineNode implements HasModifier
{
	public function __construct(
		public ?string $url = null,
		public ?int $width = null,
		public ?int $height = null,
		public ?Modifier $modifier = null,
		public ?Range $range = null,
		/** Original reference name for images written as [*ref*]; survives resolution */
		public ?string $ref = null,
		public ?Range $urlRange = null,
	) {
	}


	public function getModifier(): ?Modifier
	{
		return $this->modifier;
	}
}
