<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\{HasModifier, Range};


/**
 * Block whose source is not Texy markup: code, preformatted text, HTML or plain text.
 * Typography and the outline of headings leave it alone.
 */
abstract class VerbatimBlockNode extends BlockNode implements HasModifier
{
	public function __construct(
		public string $code,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
	) {
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
