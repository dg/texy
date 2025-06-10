<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


final readonly class Range
{
	public function __construct(
		/** 0-based offset, counted in bytes */
		public int $offset,
		/** length of the range in bytes */
		public int $length,
	) {
	}
}
