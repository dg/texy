<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;


/**
 * Texy markup whose HTML is shown as source code.
 *
 * /--texysource
 * **Texy**
 * \--
 */
class TexySourceNode extends VerbatimBlockNode
{
	public function __construct(
		string $code,
		/** the code parsed as Texy */
		public ContentNode $content = new ContentNode,
		?Texy\Modifier $modifier = null,
	) {
		parent::__construct($code, $modifier);
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
