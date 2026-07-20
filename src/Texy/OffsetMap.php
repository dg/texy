<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_column, array_pop, array_shift, count, explode, implode, min, strlen, strspn, substr;
use const PHP_INT_MAX;


/**
 * Maps offsets in a locally re-assembled string back to absolute positions in the source.
 *
 * Some block modules (blockquote, lists, tables, /--div blocks) strip per-line prefixes
 * ("> ", indentation) and join the remaining content before recursive parsing. Nodes
 * parsed from such content carry local positions; this map rewrites them to source
 * coordinates.
 */
final class OffsetMap
{
	/** @var array<int, int>  local offset of line start => absolute offset in source (ascending keys) */
	private array $map = [];


	/**
	 * @param  array<int, int>  $map  local offset of line start => absolute offset in source (ascending keys)
	 * @param  int  $skipped  number of characters trimmed from the beginning of the local string before parsing
	 */
	public function __construct(array $map = [], int $skipped = 0)
	{
		foreach ($map as $local => $absolute) {
			$this->map[$local - $skipped] = $absolute;
		}
	}


	/**
	 * Builds a map for content created by joining $lines with "\n".
	 * @param  list<array{content: string, offset: int}>  $lines  line content with its absolute source offset
	 * @param  int  $skipped  number of characters trimmed from the beginning of the joined string before parsing
	 */
	public static function fromLines(array $lines, int $skipped = 0): self
	{
		$map = [];
		$localPos = 0;
		foreach ($lines as $line) {
			$map[$localPos] = $line['offset'];
			$localPos += strlen($line['content']) + 1; // 1 = "\n"
		}

		return new self($map, $skipped);
	}


	/**
	 * Splits contiguous source text into lines with absolute offsets.
	 * @return list<array{content: string, offset: int}>
	 */
	public static function linesOf(string $text, int $offset): array
	{
		$lines = [];
		foreach (explode("\n", $text) as $line) {
			$lines[] = ['content' => $line, 'offset' => $offset];
			$offset += strlen($line) + 1; // 1 = "\n"
		}

		return $lines;
	}


	/**
	 * Outdents lines and builds the matching offset map. The transformation mirrors
	 * Helpers::outdent(): blank edge lines are dropped, then the minimal indentation
	 * (of the first line, or across all lines) is stripped from each line.
	 * @param  list<array{content: string, offset: int}>  $lines
	 * @return array{string, self}  [outdented text joined by "\n", offset map]
	 */
	public static function outdentLines(array $lines, bool $firstLine = false): array
	{
		while ($lines && $lines[0]['content'] === '') {
			array_shift($lines);
		}
		while ($lines && $lines[count($lines) - 1]['content'] === '') {
			array_pop($lines);
		}

		if ($firstLine) {
			$indent = $lines ? strspn($lines[0]['content'], ' ') : 0;
		} else {
			$indent = PHP_INT_MAX;
			foreach ($lines as $line) {
				// only lines with a non-whitespace character right after the leading
				// spaces participate, mirroring the `^\ *\S` rule of Helpers::outdent()
				$spaces = strspn($line['content'], ' ');
				$ch = $line['content'][$spaces] ?? '';
				if ($ch !== '' && strspn($ch, " \t\r\n\v\f\0") === 0) {
					$indent = min($indent, $spaces);
				}
			}
		}

		foreach ($lines as &$line) {
			$strip = min(strspn($line['content'], ' '), $indent);
			$line = ['content' => substr($line['content'], $strip), 'offset' => $line['offset'] + $strip];
		}

		return [implode("\n", array_column($lines, 'content')), self::fromLines($lines)];
	}


	/**
	 * Translates local offset to absolute source offset.
	 */
	public function toAbsolute(int $local): int
	{
		$lineStart = null;
		foreach ($this->map as $localLineStart => $_) {
			if ($localLineStart <= $local) {
				$lineStart = $localLineStart;
			} else {
				break;
			}
		}

		if ($lineStart === null) {
			$first = reset($this->map);
			return $first === false ? $local : $first;
		}

		return $this->map[$lineStart] + ($local - $lineStart);
	}


	/**
	 * Recursively rewrites node positions from local to absolute coordinates.
	 * Length of multi-line spans grows by the stripped prefixes they cover in the source.
	 */
	public function applyTo(Node $node): void
	{
		if (!$this->map) {
			return;
		}

		if ($node->range !== null) {
			$node->range = $this->rangeToAbsolute($node->range);
		}

		$modifier = $node->getModifier();
		if ($modifier?->range !== null) {
			$modifier->range = $this->rangeToAbsolute($modifier->range);
		}

		foreach ($node->getChildren() as $child) {
			$this->applyTo($child);
		}
	}


	private function rangeToAbsolute(Range $range): Range
	{
		$offset = $this->toAbsolute($range->offset);
		$end = $this->toAbsolute($range->offset + $range->length);
		return new Range($offset, $end - $offset);
	}
}
