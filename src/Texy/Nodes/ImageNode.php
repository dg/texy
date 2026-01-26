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
 * Image.
 * [* image *]
 */
class ImageNode extends InlineNode
{
	public function __construct(
		public ?string $url = null,
		public ?int $width = null,
		public ?int $height = null,
		public ?string $linkedUrl = null,
		public ?Modifier $modifier = null,
		public ?Position $position = null,
	) {
	}
}
