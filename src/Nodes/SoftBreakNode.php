<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * A newline inside a paragraph: rendered as a newline, not as a line break.
 */
class SoftBreakNode extends InlineNode
{
	public function __construct(
		public ?Range $range = null,
	) {
	}
}
