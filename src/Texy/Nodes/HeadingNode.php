<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;
use Texy\Position;


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
		public ?Position $position = null,
	) {
		(function (InlineNode ...$content) {})(...$content);
	}


	public function &getNodes(): \Generator
	{
		foreach ($this->content as &$item) {
			yield $item;
		}
	}
}
