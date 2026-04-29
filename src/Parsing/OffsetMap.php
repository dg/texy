<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use Texy\{HasModifier, Node, Range};
use function count, strlen;


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
	 * Outdents lines and builds the matching offset map (Helpers::outdent() delegates
	 * here): blank edge lines are dropped, then the whitespace prefix shared by all
	 * lines (or taken from the first line) is stripped from each line.
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
			$prefix = $lines ? substr($lines[0]['content'], 0, strspn($lines[0]['content'], " \t")) : '';
		} else {
			$prefix = null;
			foreach ($lines as $line) {
				// only lines with a non-whitespace character after the indentation participate
				$ws = strspn($line['content'], " \t");
				if (isset($line['content'][$ws])) {
					$indent = substr($line['content'], 0, $ws);
					$prefix = $prefix === null ? $indent : substr($prefix, 0, self::commonPrefixLength($prefix, $indent));
				}
			}
			$prefix ??= '';
		}

		foreach ($lines as &$line) {
			$strip = self::commonPrefixLength($line['content'], $prefix);
			$line = ['content' => substr($line['content'], $strip), 'offset' => $line['offset'] + $strip];
		}

		return [implode("\n", array_column($lines, 'content')), self::fromLines($lines)];
	}


	/**
	 * Length of the leading run shared by both strings.
	 */
	private static function commonPrefixLength(string $a, string $b): int
	{
		$i = 0;
		$max = min(strlen($a), strlen($b));
		while ($i < $max && $a[$i] === $b[$i]) {
			$i++;
		}

		return $i;
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
	 * Recursively rewrites node positions from local to absolute coordinates: every
	 * Range and OffsetMap the node holds. Length of multi-line spans grows by the
	 * stripped prefixes they cover in the source.
	 */
	public function applyTo(Node $node): void
	{
		if (!$this->map) {
			return;
		}

		foreach (get_object_vars($node) as $name => $value) {
			if ($value instanceof Range) {
				$node->$name = $this->rangeToAbsolute($value);
			} elseif ($value instanceof self) {
				$node->$name = $this->compose($value);
			}
		}

		$modifier = $node instanceof HasModifier ? $node->getModifier() : null;
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


	/**
	 * A map whose result is mapped by this one, i.e. a map of text nested in the text of this map.
	 * Mapping the entries is enough because both maps have one at every line start.
	 */
	private function compose(self $inner): self
	{
		$composed = new self;
		foreach ($inner->map as $local => $offset) {
			$composed->map[$local] = $this->toAbsolute($offset);
		}

		return $composed;
	}
}
