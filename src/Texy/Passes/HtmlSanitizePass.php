<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\Helpers;
use Texy\HtmlPolicy;
use Texy\Node;
use Texy\Nodes;
use Texy\Nodes\HtmlElementNode;
use Texy\Nodes\HtmlTagNode;
use Texy\Nodes\TextNode;
use Texy\NodeTraverser;
use function strtolower;


/**
 * Evaluates the tag whitelist and validation over the paired passthrough
 * tree in the transform phase: rejected tags and elements are replaced by
 * TextNode with the reconstructed tag source, so the AST tells the truth -
 * escaped tags are visible text (typography applies to them), allowed tags
 * are markup. Renderers keep their own checks as defense in depth.
 */
final class HtmlSanitizePass
{
	public function __construct(
		private HtmlPolicy $policy,
	) {
	}


	public function process(Nodes\DocumentNode $doc): void
	{
		(new NodeTraverser)->traverse($doc, function (Node $node): ?int {
			if ($node instanceof Nodes\ContentNode && $node->children) {
				$node->children = $this->sanitize($node->children);
			}

			return null;
		});
	}


	/**
	 * Replaces rejected tags/elements in the list by their text form.
	 * Children of a rejected element are sanitized recursively and spliced
	 * in place - once spliced they are direct children of an already
	 * processed container, so the traverser would not check them again.
	 * Children of kept elements are handled by the traverser's descent.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $children
	 * @return array<Nodes\InlineNode|Nodes\BlockNode>
	 */
	private function sanitize(array $children): array
	{
		$out = [];
		foreach ($children as $child) {
			if ($child instanceof HtmlTagNode) {
				if (!$this->policy->isTagAcceptable(strtolower($child->name), $child->attributes, $child->closing)) {
					$out[] = new TextNode(Helpers::decodeEntities($this->policy->reconstructTag($child)), $child->range);
					continue;
				}

			} elseif ($child instanceof HtmlElementNode) {
				if (!$this->policy->isTagAcceptable(strtolower($child->name), $child->attributes, closing: false)) {
					$open = new HtmlTagNode($child->name, $child->attributes, range: $child->range);
					$close = $child->closingTag ?? new HtmlTagNode($child->name, closing: true);
					$out[] = new TextNode(Helpers::decodeEntities($this->policy->reconstructTag($open)), $child->range);
					foreach ($this->sanitize($child->content->children) as $inner) {
						$out[] = $inner;
					}
					$out[] = new TextNode(Helpers::decodeEntities($this->policy->reconstructTag($close)), $close->range);
					continue;
				}
			}

			$out[] = $child;
		}

		return $out;
	}
}
