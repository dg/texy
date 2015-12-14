<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy parser base class.
 */
class Parser
{
	use Strict;

	/** @var Texy */
	protected $texy;

	/** @var HtmlElement */
	protected $element;

	/** @var array */
	public $patterns;


	/**
	 * @return Texy
	 */
	public function getTexy()
	{
		return $this->texy;
	}

}
