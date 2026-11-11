<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * Root document node.
 */
class DocumentNode extends BlockNode
{
	/** @var array<string, mixed>  document-level options collected from {{texy: ...}} directives */
	public array $meta = [];

	/** @internal */
	public TransformState $transformState = TransformState::Pending;

	/** @internal the language that parsed the document, the only one that transforms it */
	public ?string $language = null;


	public function __construct(
		public ContentNode $content = new ContentNode,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}


	/**
	 * Returns headings in document order, skipping verbatim blocks (the demo
	 * source of /--texysource is not part of the document outline).
	 * @return list<HeadingNode>
	 */
	public function getHeadings(): array
	{
		$headings = [];
		$this->traverse(function (Texy\Node $node) use (&$headings): ?Texy\TraverseAction {
			if ($node instanceof VerbatimBlockNode) {
				return Texy\TraverseAction::SkipChildren;
			}

			if ($node instanceof HeadingNode) {
				$headings[] = $node;
			}

			return null;
		});

		return $headings;
	}
}
