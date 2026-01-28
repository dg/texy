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
	/** @return \Generator<int, self, mixed, void> */
	public function &getNodes(): \Generator
	{
		// Empty generator - base implementation yields nothing
		// Subclasses override to yield their child nodes
		// Note: by-ref generator cannot use yield from, so use false && yield
		false && yield;
	}
}
