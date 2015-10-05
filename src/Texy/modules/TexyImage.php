<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Image.
 */
final class TexyImage extends TexyObject
{
	/** @var string  base image URL */
	public $URL;

	/** @var string  on-mouse-over image URL */
	public $overURL;

	/** @var string  anchored image URL */
	public $linkedURL;

	/** @var int  optional image width */
	public $width;

	/** @var int  optional image height */
	public $height;

	/** @var bool  image width and height are maximal */
	public $asMax;

	/** @var TexyModifier */
	public $modifier;

	/** @var string  reference name (if is stored as reference) */
	public $name;


	public function __construct()
	{
		$this->modifier = new TexyModifier;
	}


	public function __clone()
	{
		if ($this->modifier) {
			$this->modifier = clone $this->modifier;
		}
	}

}
