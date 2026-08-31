<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function count, sprintf;


/**
 * Writes into the source by the ranges of its syntax tree. Replacements are collected
 * and applied at once, from the end, so the ranges stay valid; two replacements that
 * overlap are an error, not a matter of which comes last.
 */
final class Rewriter
{
	/** @var list<array{int, int, string}>  from, to, text */
	private array $replacements = [];


	public function __construct(
		/** the source in the form the ranges refer to, i.e. Texy::normalize() */
		private readonly string $source,
	) {
	}


	/**
	 * Writes $new in place of the range. $expected is the text the caller expects
	 * there; a different one means the range does not belong to this source.
	 * @throws RewriteException
	 */
	public function replace(Range $range, string $new, ?string $expected = null): void
	{
		$from = $range->offset;
		$to = $from + $range->length;
		$actual = substr($this->source, $from, $range->length);

		if ($expected !== null && $actual !== $expected) {
			throw new RewriteException(sprintf("%s: expected '%s', found '%s'.", $this->describe($from), $expected, $actual));
		}

		foreach ($this->replacements as [$otherFrom, $otherTo]) {
			if ($from < $otherTo && $otherFrom < $to) {
				throw new RewriteException(sprintf("%s: the replacement of '%s' overlaps another one.", $this->describe($from), $actual));
			}
		}

		if ($actual !== $new) {
			$this->replacements[] = [$from, $to, $new];
		}
	}


	/**
	 * Number of replacements that change the source.
	 */
	public function count(): int
	{
		return count($this->replacements);
	}


	/**
	 * The source with all replacements applied. Text inserted where a replacement
	 * starts goes before it, texts inserted at the same place keep their order.
	 */
	public function getResult(): string
	{
		$replacements = $this->replacements;
		uksort(
			$replacements,
			fn($a, $b) => [$replacements[$b][0], $replacements[$b][1], $b] <=> [$replacements[$a][0], $replacements[$a][1], $a],
		);

		$result = $this->source;
		foreach ($replacements as [$from, $to, $text]) {
			$result = substr($result, 0, $from) . $text . substr($result, $to);
		}

		return $result;
	}


	private function describe(int $offset): string
	{
		$position = SourcePosition::fromOffset($this->source, $offset);
		return "Line $position->line, column $position->column";
	}
}
