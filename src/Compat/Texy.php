<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * The class name of Texy 3.
 */
class Texy extends Texy\Texy
{
	public function __construct()
	{
		trigger_error('Class Texy is deprecated, use Texy\Texy instead.', E_USER_DEPRECATED);
		parent::__construct();
	}
}
