<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Modifier;
use Texy\Position;


/**
 * Phrase (emphasis, strong, etc.).
 * *italic*, **bold**, --strikethrough--, sup^2, ...
 */
class PhraseNode extends InlineNode
{
	public function __construct(
		public ContentNode $content,
		public string $type,
		public ?Modifier $modifier = null,
		public ?Position $position = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
