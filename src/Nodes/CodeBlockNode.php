<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;


/**
 * Code block.
 *
 * /--code language
 * Code
 * \--
 */
class CodeBlockNode extends VerbatimBlockNode
{
	public function __construct(
		string $code,
		public ?string $language = null,
		?Texy\Modifier $modifier = null,
	) {
		parent::__construct($code, $modifier);
	}
}
