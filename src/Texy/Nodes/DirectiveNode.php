<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Position;


/**
 * Directive.
 * {{title My article}}
 */
class DirectiveNode extends InlineNode
{
	public function __construct(
		public string $name,
		public ?string $value,
		public array $args,
		public ?Position $position = null,
	) {
	}
}
