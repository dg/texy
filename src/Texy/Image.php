<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Image.
 */
final class Image
{
	use Strict;

	/** @var string  base image URL */
	public $URL;

	/** @var string|null  on-mouse-over image URL */
	public $overURL;

	/** @var string|null  anchored image URL */
	public $linkedURL;

	/** @var int|null  optional image width */
	public $width;

	/** @var int|null  optional image height */
	public $height;

	/** @var bool  image width and height are maximal */
	public $asMax;

	/** @var Modifier|null */
	public $modifier;

	/** @var string|null  reference name (if is stored as reference) */
	public $name;


	public function __construct()
	{
		$this->modifier = new Modifier;
	}


	public function __clone()
	{
		if ($this->modifier) {
			$this->modifier = clone $this->modifier;
		}
	}
}
