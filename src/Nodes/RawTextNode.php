<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Literal text, escaped on output and untouched by typography.
 * ''no texy''
 */
class RawTextNode extends InlineNode
{
	public function __construct(
		public string $text,
		public ?Range $range = null,
	) {
	}


	public function getPlainText(): string
	{
		return $this->text;
	}
}
