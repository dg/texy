<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


/**
 * Footnote definition.
 * [^1]: definition
 */
class FootnoteDefinitionNode extends BlockNode
{
	public function __construct(
		public string $identifier,
		/** @var array<InlineNode> */
		public array $content = [],
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
