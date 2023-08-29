<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Image.
 */
final class Image
{
	/** base image URL */
	public ?string $URL = null;

	/** anchored image URL */
	public ?string $linkedURL = null;

	/** optional image width */
	public ?int $width = null;

	/** optional image height */
	public ?int $height = null;

	/** image width and height are maximal */
	public bool $asMax = false;

	public ?Modifier $modifier;

	/** reference name (if is stored as reference) */
	public ?string $name;


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
