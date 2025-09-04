<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\{HasModifier, Range};


/**
 * Link.
 * "Link text":https://example.com
 * [Link text](https://example.com)
 */
class LinkNode extends InlineNode implements HasModifier
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
		/** name of the syntax the link is written in, e.g. Syntax::WikiLink */
		public ?string $syntax = null,
		public ?Range $textRange = null,
		/** where the target is written; for [ref] the same as $textRange */
		public ?Range $urlRange = null,
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
