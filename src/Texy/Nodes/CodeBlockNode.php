<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\HasModifier;


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
class CodeBlockNode extends BlockNode implements HasModifier
{
	public function __construct(
		public string $type,
		public string $code,
		public ?string $language = null,
		public ?Texy\Modifier $modifier = null,
		/** the code parsed, for types whose code is markup: html, pre and texysource */
		public ?ContentNode $content = null,
	) {
	}


	/** @return \Generator<ContentNode> */
	public function &getChildren(): \Generator
	{
		if ($this->content !== null) {
			yield $this->content;
		}
	}


	public function getModifier(): ?Texy\Modifier
	{
		return $this->modifier;
	}
}
