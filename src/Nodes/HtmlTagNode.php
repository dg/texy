<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * HTML tag.
 * <article title=hello>
 */
class HtmlTagNode extends InlineNode
{
	public function __construct(
		public string $name,
		/** @var array<string, string|bool|null> HTML attributes */
		public array $attributes = [],
		public bool $closing = false,
		public bool $selfClosing = false,
		public ?Range $range = null,
	) {
	}


	/**
	 * Reconstructs the original tag source text.
	 */
	public function toSource(): string
	{
		$tag = '<';
		if ($this->closing) {
			$tag .= '/';
		}
		$tag .= $this->name;
		foreach ($this->attributes as $name => $value) {
			$tag .= ' ' . $name;
			if ($value !== true) {
				$tag .= '="' . $value . '"';
			}
		}
		if ($this->selfClosing) {
			$tag .= ' /';
		}

		return $tag . '>';
	}
}
