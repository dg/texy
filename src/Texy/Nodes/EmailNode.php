<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


/**
 * Email link.
 * example@example.com
 */
class EmailNode extends InlineNode
{
	public function __construct(
		public string $email,
	) {
	}
}
