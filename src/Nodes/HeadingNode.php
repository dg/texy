<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\{HasModifier, Range};


/**
 * Heading.
 * -------
 *
 * # Heading 1
 */
class HeadingNode extends BlockNode implements HasModifier
{
	/** the {toc: ...} label, hoisted out of the modifier; null means the content is the title */
	public ?string $titleOverride = null;

	/** the heading's anchor, filled by HeadingPass; an explicit .[#id] the policy allows wins over the generated one */
	public ?string $id = null;


	public function __construct(
		public ContentNode $content,
		/** 1..6, by the notation in the syntax tree and the real one in a transformed document */
		public int $level,
		public HeadingType $type,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
		/** the underline character or the opening sequence as written, e.g. '-' or '###' */
		public ?string $marker = null,
		public ?Range $textRange = null,
	) {
	}


	/**
	 * The heading as plain text, for the document title, the outline and the ID slug.
	 * Derived from the content, not copied, so the two cannot drift apart.
	 */
	public function getTitle(): string
	{
		return $this->titleOverride ?? trim($this->getPlainText());
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
