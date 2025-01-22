<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Directive.
 * {{title My article}}
 */
class DirectiveNode extends Texy\Node
{
	public function __construct(
		public string $name,
		public ?string $value,
	) {
	}
}
