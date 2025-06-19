<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\HasModifier;


/**
 * Heading.
 * -------
 *
 * # Heading 1
 */
class HeadingNode extends BlockNode implements HasModifier
{
	public function __construct(
		public ContentNode $content,
		public int $level,
		public HeadingType $type,
		public ?Texy\Modifier $modifier = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
