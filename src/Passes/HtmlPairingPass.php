<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\{Node, Nodes, NodeTraverser, Output, TraverseAction};
use Texy\Nodes\{HtmlElementNode, HtmlTagNode};
use function count;


/**
 * Pairs passthrough HtmlTagNode open/close tags into HtmlElementNode with
 * real children, turning the tag stream into a tree (transform phase).
 *
 * Pairing is per content container and tolerant: crossed or unmatched tags
 * stay as standalone HtmlTagNode. Void elements are leaves by definition.
 */
final class HtmlPairingPass
{
	public function process(Nodes\DocumentNode $doc): void
	{
		(new NodeTraverser)->traverse($doc, function (Node $node): ?TraverseAction {
			if ($node instanceof Nodes\ContentNode && $node->children) {
				$node->children = $this->pair($node->children);
			}

			return null;
		});
	}


	/**
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $children
	 * @return array<Nodes\InlineNode|Nodes\BlockNode>
	 */
	private function pair(array $children): array
	{
		$out = [];
		$frames = []; // stack of [open HtmlTagNode, collected children]
		$open = []; // number of open frames per tag name

		foreach ($children as $child) {
			if (!$child instanceof HtmlTagNode) {
				self::emit($out, $frames, $child);
				continue;
			}

			$name = strtolower($child->name);

			if (!$child->closing && !$child->selfClosing && !isset(Output\Html\Schema::voidElements()[$name])) {
				$frames[] = [$child, []];
				$open[$name] = ($open[$name] ?? 0) + 1;
				continue;
			}

			if (!$child->closing || empty($open[$name])) { // void or self-closing tag is a leaf, so is a stray closing tag
				self::emit($out, $frames, $child);
				continue;
			}

			// closing tag: find matching open frame from the top
			$found = count($frames) - 1;
			while (($frameName = strtolower($frames[$found][0]->name)) !== $name) {
				$open[$frameName]--;
				$found--;
			}

			// crossed tags above the match cannot be paired - flatten them back
			self::flatten($out, $frames, $found + 1);
			$open[$name]--;

			$frame = array_pop($frames);
			assert($frame !== null);
			[$tag, $collected] = $frame;
			self::emit(
				$out,
				$frames,
				new HtmlElementNode($tag->name, $tag->attributes, new Nodes\ContentNode($collected), $child, $tag->range, $tag->source),
			);
		}

		// unmatched opening tags stay standalone
		self::flatten($out, $frames, 0);

		return $out;
	}


	/**
	 * Emits node into the innermost open frame, or the output list.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $out
	 * @param  list<array{HtmlTagNode, array<Nodes\InlineNode|Nodes\BlockNode>}>  $frames
	 */
	private static function emit(array &$out, array &$frames, Nodes\InlineNode|Nodes\BlockNode $node): void
	{
		if ($frames) {
			$frame = array_pop($frames);
			$frame[1][] = $node;
			$frames[] = $frame;
		} else {
			$out[] = $node;
		}
	}


	/**
	 * Pops the frames from $depth up and re-emits their open tags and children unpaired.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $out
	 * @param  list<array{HtmlTagNode, array<Nodes\InlineNode|Nodes\BlockNode>}>  $frames
	 */
	private static function flatten(array &$out, array &$frames, int $depth): void
	{
		$popped = array_slice($frames, $depth); // array_splice() would rebuild the whole stack
		while (count($frames) > $depth) {
			array_pop($frames);
		}

		foreach ($popped as [$tag, $collected]) {
			self::emit($out, $frames, $tag);
			foreach ($collected as $node) {
				self::emit($out, $frames, $node);
			}
		}
	}
}
