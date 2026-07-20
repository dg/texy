<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\Helpers;
use Texy\Nodes;
use Texy\Nodes\HeadingNode;
use Texy\Nodes\HeadingType;


/**
 * Balances heading levels, hoists the {toc: ...} style onto the heading node
 * and generates heading IDs (transform phase). Results live in the AST, so any
 * consumer can read them from the document alone via HeadingNode::collectFrom().
 */
final class HeadingPass
{
	public function __construct(
		private int $top = 1,
		private bool $dynamicBalancing = true,
		private bool $generateID = false,
		private string $idPrefix = 'toc-',
	) {
	}


	public function process(Nodes\DocumentNode $doc): void
	{
		$headings = HeadingNode::collectFrom($doc);
		if (!$headings) {
			return;
		}

		$this->balanceLevels($headings);

		$usedID = [];
		foreach ($headings as $node) {
			$node->titleOverride = self::hoistOverride($node);
			if (!$this->generateID) {
				continue;
			}

			if ($node->modifier?->id) { // an explicit ID wins and reserves the name
				$usedID[$node->modifier->id] = true;
				$node->id = $node->modifier->id;
				continue;
			}

			// runs before typography, so the slug does not depend on it
			$id = self::uniqueID($this->idPrefix . Helpers::webalize($node->getTitle()), $usedID);
			$usedID[$id] = true;
			$node->id = $id;
		}
	}


	/** @param  list<HeadingNode>  $headings */
	private function balanceLevels(array $headings): void
	{
		if (!$this->dynamicBalancing) {
			foreach ($headings as $node) {
				$node->level = min(6, max(1, $node->level + $this->top));
			}

			return;
		}

		$map = [];
		$min = 100;

		// collect level information
		foreach ($headings as $node) {
			match ($node->type) {
				HeadingType::Surrounded => $min = min($node->level, $min),
				HeadingType::Underlined => $map[$node->level] = $node->level,
			};
		}

		// top offset for surrounded headings
		$top = $this->top - $min;

		// sort underlined levels and create mapping
		asort($map);
		$map = array_flip(array_values($map));

		// apply calculated levels
		foreach ($headings as $node) {
			$level = match ($node->type) {
				HeadingType::Surrounded => $node->level + $top,
				HeadingType::Underlined => $map[$node->level] + $this->top,
			};
			$node->level = min(6, max(1, $level));
		}
	}


	/**
	 * Moves the {toc: ...} label out of the modifier; it is transport syntax and must
	 * never reach the output.
	 */
	private static function hoistOverride(HeadingNode $node): ?string
	{
		if (!isset($node->modifier->styles['toc'])) {
			return null;
		}

		$title = $node->modifier->styles['toc'];
		unset($node->modifier->styles['toc']);
		return $title;
	}


	/** @param  array<string, true>  $usedID */
	private static function uniqueID(string $id, array $usedID): string
	{
		if (!isset($usedID[$id])) {
			return $id;
		}

		$counter = 2;
		while (isset($usedID[$id . '-' . $counter])) {
			$counter++;
		}

		return $id . '-' . $counter;
	}
}
