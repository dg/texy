<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function strlen;


/**
 * Line and column of a position in the source, as an editor shows it.
 */
final readonly class SourcePosition
{
	public function __construct(
		/** 1-based */
		public int $line,
		/** 1-based, counted in characters */
		public int $column,
	) {
	}


	public static function fromOffset(string $source, int $offset): self
	{
		$before = substr($source, 0, $offset);
		$lineStart = strrpos($before, "\n");
		$lineText = $lineStart === false ? $before : substr($before, $lineStart + 1);
		return new self(
			substr_count($before, "\n") + 1,
			strlen($lineText) - (int) preg_match_all('~[\x80-\xBF]~', $lineText) + 1, // continuation bytes start no character
		);
	}
}
