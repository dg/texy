<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


final class Position
{
	public function __construct(
		public readonly int $offset,
		public readonly int $length,
	) {
	}
}
