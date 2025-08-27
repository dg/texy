<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Link definition.
 * [ref]: https://example.com
 */
class LinkDefinitionNode extends BlockNode
{
	public function __construct(
		public string $reference,
		public string $url,
		public ?Range $range = null,
	) {
	}
}
