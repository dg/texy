<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Horizontal rule.
 * ---
 */
class HorizontalRuleNode extends BlockNode
{
	public function __construct(
		public string $type,
		public ?Texy\Modifier $modifier = null,
	) {
	}
}
