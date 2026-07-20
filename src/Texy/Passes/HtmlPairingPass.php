<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\Node;
use Texy\Nodes;
use Texy\Nodes\HtmlElementNode;
use Texy\Nodes\HtmlTagNode;
use Texy\NodeTraverser;
use Texy\Output;
use function array_pop, count, strtolower;


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
		(new NodeTraverser)->traverse($doc, function (Node $node): ?int {
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

		foreach ($children as $child) {
			if (!$child instanceof HtmlTagNode) {
				self::emit($out, $frames, $child);
				continue;
			}

			$name = strtolower($child->name);

			if (!$child->closing && !$child->selfClosing && !isset(Output\Html\Schema::voidElements()[$name])) {
				$frames[] = [$child, []];
				continue;
			}

			if (!$child->closing) { // void or self-closing tag is a leaf
				self::emit($out, $frames, $child);
				continue;
			}

			// closing tag: find matching open frame from the top
			$found = null;
			for ($i = count($frames) - 1; $i >= 0; $i--) {
				if (strtolower($frames[$i][0]->name) === $name) {
					$found = $i;
					break;
				}
			}

			if ($found === null) { // stray closing tag
				self::emit($out, $frames, $child);
				continue;
			}

			// crossed tags above the match cannot be paired - flatten them back
			while (count($frames) - 1 > $found) {
				self::flatten($out, $frames);
			}

			$frame = array_pop($frames);
			assert($frame !== null);
			[$tag, $collected] = $frame;
			self::emit($out, $frames, new HtmlElementNode($tag->name, $tag->attributes, new Nodes\ContentNode($collected), $child, $tag->range));
		}

		// unmatched opening tags stay standalone
		while ($frames) {
			self::flatten($out, $frames);
		}

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
	 * Pops the innermost frame and re-emits its open tag and children unpaired.
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $out
	 * @param  list<array{HtmlTagNode, array<Nodes\InlineNode|Nodes\BlockNode>}>  $frames
	 */
	private static function flatten(array &$out, array &$frames): void
	{
		$frame = array_pop($frames);
		assert($frame !== null);
		[$tag, $collected] = $frame;
		self::emit($out, $frames, $tag);
		foreach ($collected as $node) {
			self::emit($out, $frames, $node);
		}
	}
}
