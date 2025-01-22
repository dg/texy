<?php

/**
 * This file is part of the Texy! (https://texy.info)
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
	public const Underlined = 1;
	public const Surrounded = 2;


	public function __construct(
		/** @var array<InlineNode> */
		public array $content,
		public int $level,
		public int $type,
		public ?Texy\Modifier $modifier = null,
	) {
		(function (InlineNode ...$content) {})(...$content);
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
