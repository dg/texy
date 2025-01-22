<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Phrase (emphasis, strong, etc.).
 * *italic*, **bold**, --strikethrough--, sup^2, ...
 */
class PhraseNode extends InlineNode
{
	public function __construct(
		/** @var array<InlineNode> */
		public array $content = [],
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
