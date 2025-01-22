<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


/**
 * HTML tag.
 * <article title=hello>
 */
class HtmlTagNode extends InlineNode
{
	public function __construct(
		public string $name,
		public array $attributes = [],
		public bool $closing = false,
		public bool $selfClosing = false,
	) {
	}
}
