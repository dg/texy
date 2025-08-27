<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * URL.
 * https://example.com
 */
class UrlNode extends InlineNode
{
	public function __construct(
		public string $url,
		public ?Range $range = null,
	) {
	}
}
