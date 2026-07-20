<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;


/**
 * HTML tag.
 * <article title=hello>
 */
class HtmlTagNode extends InlineNode
{
	public function __construct(
		public string $name,
		/** @var array<string, string|bool> */
		public array $attributes = [],
		public bool $closing = false,
		public bool $selfClosing = false,
	) {
	}
}
