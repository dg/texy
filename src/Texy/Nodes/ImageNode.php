<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Modifier;


/**
 * Image.
 * [* image *]
 */
class ImageNode extends InlineNode
{
	public function __construct(
		public ?string $url = null,
		public ?int $width = null,
		public ?int $height = null,
		public ?Modifier $modifier = null,
		/** Original reference name for images written as [*ref*]; survives resolution */
		public ?string $ref = null,
	) {
	}
}
