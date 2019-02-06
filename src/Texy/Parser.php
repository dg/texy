<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Texy parser base class.
 */
class Parser
{
	use Strict;

	/** @var array */
	public $patterns;

	/** @var Texy */
	protected $texy;

	/** @var HtmlElement */
	protected $element;


	public function getTexy(): Texy
	{
		return $this->texy;
	}
}
