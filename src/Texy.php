<?php

/**
 * Texy! is human-readable text to HTML converter (https://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);


if (false) {
	class Texy extends Texy\Texy
	{
	}
} elseif (!class_exists(Texy::class)) {
	class_alias(Texy\Texy::class, Texy::class);
}
