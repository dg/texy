<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;


/**
 * Paragraph.
 */
class ParagraphNode extends BlockNode
{
	/** contains block-level HTML elements, skip <p> wrapper */
	public bool $blockHtml = false;


	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
