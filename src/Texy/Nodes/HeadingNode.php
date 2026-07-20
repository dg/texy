<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * Heading.
 * -------
 *
 * # Heading 1
 */
class HeadingNode extends BlockNode
{
	/** title for the table of contents, filled in the transform phase (before typography) */
	public ?string $tocTitle = null;


	public function __construct(
		public ContentNode $content,
		public int $level,
		public HeadingType $type,
		public ?Texy\Modifier $modifier = null,
		public ?Range $range = null,
	) {
	}


	/**
	 * Returns headings in document order, skipping texysource sections (demo
	 * sources are not part of the document outline).
	 * @return list<self>
	 */
	public static function collectFrom(Texy\Node $root): array
	{
		$headings = [];
		(new Texy\NodeTraverser)->traverse($root, function (Texy\Node $node) use (&$headings): ?int {
			if ($node instanceof SectionNode && $node->type === 'texysource') {
				return Texy\NodeTraverser::DontTraverseChildren;
			}

			if ($node instanceof self) {
				$headings[] = $node;
			}

			return null;
		});

		return $headings;
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
