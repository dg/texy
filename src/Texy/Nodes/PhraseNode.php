<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\{HasModifier, Modifier};


/**
 * Phrase (emphasis, strong, etc.).
 * *italic*, **bold**, --strikethrough--, sup^2, ...
 */
class PhraseNode extends InlineNode implements HasModifier
{
	public function __construct(
		public ContentNode $content,
		public string $type,
		public ?Modifier $modifier = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}


	public function getModifier(): ?Modifier
	{
		return $this->modifier;
	}
}
