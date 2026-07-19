<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Root document node.
 */
class DocumentNode extends BlockNode
{
	/** @var array<string, mixed>  document-level options collected from {{texy: ...}} directives */
	public array $meta = [];


	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
