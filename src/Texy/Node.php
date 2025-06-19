<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Base class for AST nodes representing parsed document.
 */
abstract class Node
{
	/** @return \Generator<self> */
	public function &getChildren(): \Generator
	{
		false && yield;
	}


	/**
	 * Yields a child list by reference and prunes removed items. Iterate it, never return it.
	 * @template T of self
	 * @param  array<?T>|null  $list
	 * @param  ?\Closure(T ...): void  $type  re-checks item types after the walk
	 * @return \Generator<T>
	 */
	protected static function &walk(?array &$list, ?\Closure $type = null): \Generator
	{
		if ($list === null) {
			return;
		}

		try {
			foreach ($list as &$item) {
				yield $item;
			}
		} finally { // StopTraversal discards the generator before its end
			if (in_array(null, $list, true)) {
				$list = array_values(array_filter($list, fn($item) => $item !== null));
			}

			$type?->__invoke(...$list);
		}
	}
}
