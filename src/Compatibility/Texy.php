<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


if (false) {
	class Texy extends Texy\Texy
	{
	}
} elseif (!class_exists(Texy::class)) {
	class_alias(Texy\Texy::class, Texy::class);
}
