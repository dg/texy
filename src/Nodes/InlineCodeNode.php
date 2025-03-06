<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\HasModifier;


/**
 * Inline code, shown as written.
 * `code`
 */
class InlineCodeNode extends InlineNode implements HasModifier
{
	public function __construct(
		public string $code,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function getPlainText(): string
	{
		return $this->code;
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
