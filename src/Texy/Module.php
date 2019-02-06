<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Texy! modules base class.
 */
abstract class Module
{
	use Strict;

	/** @var Texy */
	protected $texy;
}
