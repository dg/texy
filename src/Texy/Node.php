<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * @implements \IteratorAggregate<Node>
 */
abstract class Node implements \IteratorAggregate
{
	public ?int $startPos = null;
	public ?int $endPos = null;


	/** @return \Generator<self> */
	public function &getIterator(): \Generator
	{
		false && yield;
	}
}
