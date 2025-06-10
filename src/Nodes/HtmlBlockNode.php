<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * HTML passed through to the output.
 *
 * /--html
 * <b>HTML</b>
 * \--
 */
class HtmlBlockNode extends VerbatimBlockNode
{
	public function __construct(
		string $code,
		/** the code parsed for its HTML tags, comments and entities */
		public ContentNode $content = new ContentNode,
		?Texy\Modifier $modifier = null,
		?Range $range = null,
	) {
		parent::__construct($code, $modifier, $range);
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
