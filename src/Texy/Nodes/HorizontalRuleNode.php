<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\HasModifier;


/**
 * Horizontal rule.
 * ---
 */
class HorizontalRuleNode extends BlockNode implements HasModifier
{
	public function __construct(
		public string $type,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
