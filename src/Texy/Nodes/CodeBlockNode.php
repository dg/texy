<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


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
		public string $code,
		public ?string $language = null,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
	) {
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
