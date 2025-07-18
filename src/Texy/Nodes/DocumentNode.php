<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;


/**
 * Root document node.
 */
class DocumentNode extends BlockNode
{
	/** @var array<string, mixed>  document-level options collected from {{texy: ...}} directives */
	public array $meta = [];


	public function __construct(
		public ContentNode $content = new ContentNode,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
