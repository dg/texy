<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Base class for AST nodes representing parsed document.
 */
abstract class Node
{
	/** @return \Generator<int, self, mixed, void> */
	public function &getChildren(): \Generator
	{
		false && yield;
	}
}
