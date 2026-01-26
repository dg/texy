<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Base class for AST nodes representing parsed document.
 */
abstract class Node
{
	public ?Position $position = null;


	/** @return \Generator<self> */
	public function &getNodes(): \Generator
	{
		false && yield;
	}
}
