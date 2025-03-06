<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;


/**
 * HTML comment.
 * <!-- content -->
 */
class HtmlCommentNode extends InlineNode
{
	public function __construct(
		public string $text,
	) {
	}
}
