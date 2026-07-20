<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


final class Range
{
	public function __construct(
		/** 0-based offset, counted in bytes */
		public readonly int $offset,
		/** length of the range in bytes */
		public readonly int $length,
	) {
	}
}
