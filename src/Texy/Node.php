<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function in_array;


/**
 * Base class for AST nodes representing parsed document.
 */
abstract class Node
{
	public ?Range $range = null;


	/** @return \Generator<self> */
	public function &getChildren(): \Generator
	{
		false && yield;
	}


	/**
	 * @template T of self
	 * @param  array<?T>  $list
	 * @return \Generator<T>
	 */
	protected function &yieldList(array &$list): \Generator
	{
		try {
			foreach ($list as &$item) {
				yield $item;
			}
		} finally {
			if (in_array(null, $list, true)) {
				$list = array_values(array_filter($list, fn($item) => $item !== null));
			}
		}
	}
}
