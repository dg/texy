<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\{Config, Helpers, Nodes};
use Texy\Nodes\HeadingNode;


/**
 * Shifts heading levels by Config::$headingTop and generates heading IDs (transform phase), for a
 * document in any language. Results live in the AST, so any consumer can read them
 * from the document alone via DocumentNode::getHeadings().
 */
final class HeadingPass
{
	public function __construct(
		private Config $config,
	) {
	}


	public function process(Nodes\DocumentNode $doc): void
	{
		$headings = $doc->getHeadings();
		foreach ($headings as $node) {
			$node->level = min(6, max(1, $node->level + $this->config->headingTop - 1));
		}

		if ($this->config->headingIds) {
			$this->generateIDs($headings);
		}
	}


	/**
	 * Explicit IDs reserve their names first, so a generated one never collides
	 * with an explicit one later in the document. HtmlSanitizePass has removed those the policy rejects.
	 * @param  list<HeadingNode>  $headings
	 */
	private function generateIDs(array $headings): void
	{
		$usedID = [];
		foreach ($headings as $node) {
			if ($node->modifier?->id) {
				$usedID[$node->modifier->id] = true;
				$node->id = $node->modifier->id;
			}
		}

		foreach ($headings as $node) {
			if ($node->id === null) {
				// runs before typography, so the slug does not depend on it
				$id = self::uniqueID($this->config->headingIdPrefix . Helpers::webalize($node->getTitle()), $usedID);
				$usedID[$id] = true;
				$node->id = $id;
			}
		}
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
