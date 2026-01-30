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
 * Paragraph.
 */
class ParagraphNode extends BlockNode
{
	/** contains block-level HTML elements, skip <p> wrapper */
	public bool $blockHtml = false;


	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Texy\Modifier $modifier = null,
		public ?Position $position = null,
	) {
	}


	public function &getNodes(): \Generator
	{
		yield $this->content;
	}
}
