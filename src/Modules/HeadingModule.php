<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Range, Syntax};
use Texy\Nodes\{HeadingNode, HeadingType};
use Texy\Parsing\ParseContext;
use function strlen;


/**
 * Processes heading syntax (underlined and surrounded), balances the levels by the order of
 * heading styles in the document and generates heading IDs.
 */
final class HeadingModule extends Texy\Module
{
	#[\Deprecated('use HeadingBalancing::Dynamic', since: '4.0')]
	public const DYNAMIC = Texy\HeadingBalancing::Dynamic;

	#[\Deprecated('use HeadingBalancing::Fixed', since: '4.0')]
	public const FIXED = Texy\HeadingBalancing::Fixed;


	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->addBlock(
			Syntax::HeadingUnderlined,
			'~^
				( \S .{0,1000} )                 # heading text (1)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (2)
				\n
				( \#{3,}+ | \*{3,}+ | ={3,}+ | -{3,}+ )  # underline characters (3)
			$~mUx',
			$this->parseUnderline(...),
		);

		$registry->addBlock(
			Syntax::HeadingSurrounded,
			'~^
				( \#{2,}+ | ={2,}+ )             # opening characters (1)
				(.+)                             # heading text (2)
				' . Texy\Patterns::MODIFIER_H . '? # modifier (3)
			$~mUx',
			$this->parseSurround(...),
		);
	}


	public function processHeadings(Texy\Nodes\DocumentNode $document): void
	{
		$headings = $document->getHeadings();
		if ($this->config->headingBalancing === Texy\HeadingBalancing::Dynamic) {
			$this->balanceLevels($headings);
		}

		foreach ($headings as $node) {
			$node->level = min(6, max(1, $node->level + $this->config->headingTop - 1));
		}

		if ($this->config->headingIds) {
			$this->generateIDs($headings);
		}
	}


	/**
	 * Levels are read from the notation, the level of a node may have been changed or cut off.
	 * @param  list<HeadingNode>  $headings
	 */
	private function balanceLevels(array $headings): void
	{
		$underlined = [];
		$top = PHP_INT_MAX;
		foreach ($headings as $node) {
			if ($node->marker !== null) {
				$level = $this->readLevel($node->marker, $node->type);
				if ($node->type === HeadingType::Underlined) {
					$underlined[$level] = $level;
				} else {
					$top = min($top, $level);
				}
			}
		}

		asort($underlined);
		$order = array_flip(array_values($underlined));

		foreach ($headings as $node) {
			if ($node->marker !== null) {
				$level = $this->readLevel($node->marker, $node->type);
				$node->level = min(6, match ($node->type) {
					HeadingType::Surrounded => $level - $top + 1,
					HeadingType::Underlined => $order[$level] + 1,
				});
			}
		}
	}


	/** The level as written, by the notation alone. */
	private function readLevel(string $marker, HeadingType $type): int
	{
		if ($type === HeadingType::Underlined) {
			return $this->config->headingLevels[$marker[0]]
				?? throw new Texy\InvalidArgumentException("TexyConfig::\$headingLevels has no level for '$marker[0]'.");
		}

		$length = min(7, max(2, strlen($marker)));
		return $this->config->headingMoreMeansHigher ? 8 - $length : $length - 1;
	}


	/**
	 * The {toc: ...} label gives the slug instead of the heading's text.
	 * @param  list<HeadingNode>  $headings
	 */
	private function generateIDs(array $headings): void
	{
		$usedID = [];
		foreach ($headings as $node) {
			$title = self::hoistOverride($node) ?? trim($node->getPlainText());
			if ($node->modifier?->id) {
				$usedID[$node->modifier->id] = true;
				continue;
			}

			$id = self::uniqueID($this->config->headingIdPrefix . Texy\Helpers::webalize($title), $usedID);
			$usedID[$id] = true;
			$node->modifier ??= new Modifier;
			$node->modifier->id = $id;
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


	/**
	 * Parses underlined heading.
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	public function parseUnderline(ParseContext $context, array $matches, array $offsets): HeadingNode
	{
		[, $mContent, $mMod, $mLine] = $matches;
		$contentOffset = $offsets[1];
		return new HeadingNode(
			$context->parseInline(trim($mContent), $contentOffset),
			$this->readLevel($mLine, HeadingType::Underlined),
			HeadingType::Underlined,
			Modifier::parse($mMod, $offsets[2] ?? null),
			new Range($offsets[0], strlen($matches[0])),
			$mLine[0],
			new Range($contentOffset, strlen(rtrim($mContent))),
		);
	}


	/**
	 * Parses surrounded heading.
	 * @param  array{string, string, string, ?string}  $matches
	 * @param  array{int, int, int, ?int}  $offsets
	 */
	public function parseSurround(ParseContext $context, array $matches, array $offsets): HeadingNode
	{
		[, $mLine, $mContent, $mMod] = $matches;
		$mContent = rtrim($mContent, $mLine[0] . ' ');
		$contentOffset = $offsets[2];

		// Adjust offset for leading whitespace removed by trim
		$trimmed = ltrim($mContent);
		$leadingSpaces = strlen($mContent) - strlen($trimmed);
		$contentOffset += $leadingSpaces;

		return new HeadingNode(
			$context->parseInline(trim($mContent), $contentOffset),
			$this->readLevel($mLine, HeadingType::Surrounded),
			HeadingType::Surrounded,
			Modifier::parse($mMod, $offsets[3] ?? null),
			new Range($offsets[0], strlen($matches[0])),
			$mLine,
			new Range($contentOffset, strlen($trimmed)),
		);
	}
}
