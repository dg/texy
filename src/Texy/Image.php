<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Stores image URL, dimensions, modifiers, and reference info.
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

	public Modifier $modifier;

	/** reference name (if is stored as reference) */
	public ?string $name;


	public function __construct()
	{
		$this->modifier = new Modifier;
	}


	public function __clone()
	{
		$this->modifier = clone $this->modifier;
	}
}
