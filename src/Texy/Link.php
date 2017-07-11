<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Link.
 */
final class Link
{
	use Strict;

	/** @see $type */
	const
		COMMON = 1,
		BRACKET = 2,
		IMAGE = 3;

	/** @var string  URL in resolved form */
	public $URL;

	/** @var string  URL as written in text */
	public $raw;

	/** @var Modifier */
	public $modifier;

	/** @var int  how was link created? */
	public $type = self::COMMON;

	/** @var string  optional label, used by references */
	public $label;

	/** @var string  reference name (if is stored as reference) */
	public $name;


	public function __construct($URL)
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
