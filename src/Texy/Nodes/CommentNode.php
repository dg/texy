<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;


/**
 * Comment.
 * /--comment
 * \--
 */
class CommentNode extends BlockNode
{
	public function __construct(
		public string $text,
	) {
	}
}
