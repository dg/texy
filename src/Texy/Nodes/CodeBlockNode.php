<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy;


/**
 * Code block.
 *
 * /--language
 * Code
 * \--
 *
 * ```language
 * Code
 * ```
 */
class CodeBlockNode extends BlockNode
{
	public function __construct(
		public string $type,
		public string $content,
		public ?string $language = null,
		public ?Texy\Modifier $modifier = null,
	) {
	}
}
