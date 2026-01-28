<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


/**
 * Link reference.
 * [1]
 */
class LinkReferenceNode extends InlineNode
{
	public function __construct(
		public string $identifier,
	) {
	}
}
