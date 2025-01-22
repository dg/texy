<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Link definition.
 * [1]: https://example.com
 */
class LinkReferenceNode extends Texy\Node
{
	public function __construct(
		public string $identifier,
		public string $url,
	) {
	}
}
