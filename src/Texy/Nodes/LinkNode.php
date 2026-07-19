<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * Link.
 * "Link text":https://example.com
 * [Link text](https://example.com)
 */
class LinkNode extends InlineNode
{
	public function __construct(
		public ?string $url = null,
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
		/** Link targets an image URL (use imageModule.root instead of linkModule.root) */
		public bool $isImageLink = false,
		/** Original reference name for links written as [ref]; survives resolution */
		public ?string $ref = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
