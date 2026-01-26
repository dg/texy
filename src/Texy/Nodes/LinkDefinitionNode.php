<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;
use Texy\Position;


/**
 * Link definition.
 * [ref]: https://example.com label .(title)[class]{style}
 */
class LinkDefinitionNode extends BlockNode
{
	public function __construct(
		public string $identifier,
		public string $url,
		public ?string $label = null,
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
	) {
	}
}
