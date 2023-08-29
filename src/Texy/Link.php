<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Link.
 */
final class Link
{
	/** @see $type */
	public const
		COMMON = 1,
		BRACKET = 2,
		IMAGE = 3;

	/** URL in resolved form */
	public ?string $URL;

	/** URL as written in text */
	public string $raw;

	public Modifier $modifier;

	/** how was link created? */
	public int $type = self::COMMON;

	/** optional label, used by references */
	public ?string $label = null;

	/** reference name (if is stored as reference) */
	public ?string $name = null;


	public function __construct(string $URL)
	{
		$this->URL = $URL;
		$this->raw = $URL;
		$this->modifier = new Modifier;
	}


	public function __clone()
	{
		if ($this->modifier) {
			$this->modifier = clone $this->modifier;
		}
	}
}
