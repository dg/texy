<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Heading.
 * -------
 *
 * # Heading 1
 */
class HeadingNode extends BlockNode
{
	public function __construct(
		public ContentNode $content,
		public int $level,
		public HeadingType $type,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
