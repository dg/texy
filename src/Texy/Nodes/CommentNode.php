<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Position;


/**
 * Comment.
 * /--comment
 * \--
 */
class CommentNode extends BlockNode
{
	public function __construct(
		public string $content,
		public ?Position $position = null,
	) {
	}
}
