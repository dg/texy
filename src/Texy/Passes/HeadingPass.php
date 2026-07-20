<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\Helpers;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Nodes\HeadingNode;
use Texy\Nodes\HeadingType;
use function array_flip, array_values, asort, max, min, trim;


/**
 * Balances heading levels, hoists the {toc: ...} style into HeadingNode::$tocTitle
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
			$node->tocTitle = $title = self::hoistTitle($node);
			if (!$this->generateID) {
				continue;
			}

			if ($node->modifier?->id) { // an explicit ID wins and reserves the name
				$usedID[$node->modifier->id] = true;
				continue;
			}

			$id = self::uniqueID($this->idPrefix . Helpers::webalize($title), $usedID);
			$usedID[$id] = true;
			$node->modifier ??= new Modifier;
			$node->modifier->id = $id;
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
	 * Returns the title for the table of contents; the {toc: ...} style is
	 * transport syntax, so it is removed from the modifier.
	 */
	private static function hoistTitle(HeadingNode $node): string
	{
		if (isset($node->modifier->styles['toc'])) {
			$title = $node->modifier->styles['toc'];
			unset($node->modifier->styles['toc']);
			return $title;
		}

		return trim(Helpers::extractText($node));
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
