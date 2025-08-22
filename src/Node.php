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
	public ?Range $range = null;


	/** @return \Generator<self> */
	public function &getChildren(): \Generator
	{
		false && yield;
	}


	/**
	 * Finds the nodes of the class in the subtree, this one included, in document order.
	 * @template T of self
	 * @param  class-string<T>  $class
	 * @param  ?\Closure(T): bool  $filter
	 * @return list<T>
	 */
	public function find(string $class, ?\Closure $filter = null): array
	{
		$found = [];
		$this->traverse(function (self $node) use ($class, $filter, &$found) {
			if ($node instanceof $class && ($filter === null || $filter($node))) {
				$found[] = $node;
			}

			return null;
		});
		return $found;
	}


	/**
	 * Finds the first node of the class in the subtree, this one included.
	 * @template T of self
	 * @param  class-string<T>  $class
	 * @param  ?\Closure(T): bool  $filter
	 * @return ?T
	 */
	public function findFirst(string $class, ?\Closure $filter = null): ?self
	{
		$found = null;
		$this->traverse(function (self $node) use ($class, $filter, &$found) {
			if ($node instanceof $class && ($filter === null || $filter($node))) {
				$found = $node;
				return TraverseAction::Stop;
			}

			return null;
		});
		return $found;
	}


	/**
	 * Walks the subtree; a callback may return a replacement node or a TraverseAction. Returns this
	 * node, its replacement, or null when it was removed.
	 * @param  ?\Closure(self): (self|TraverseAction|null)  $enter
	 * @param  ?\Closure(self): (self|TraverseAction|null)  $leave
	 */
	public function traverse(?\Closure $enter = null, ?\Closure $leave = null): ?self
	{
		return (new NodeTraverser)->traverse($this, $enter, $leave);
	}


	/**
	 * The text of the subtree without any markup.
	 */
	public function getPlainText(): string
	{
		$text = '';
		foreach ($this->getChildren() as $child) {
			$text .= $child->getPlainText();
		}

		return $text;
	}


	/** A deep copy: every node of the subtree is new, and so is its modifier. */
	public function __clone()
	{
		if ($this instanceof HasModifier && $this->modifier !== null) {
			$this->modifier = clone $this->modifier;
		}

		foreach ($this->getChildren() as &$child) {
			$child = clone $child;
		}
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
		} finally { // TraverseAction::Stop discards the generator before its end
			if (in_array(null, $list, true)) {
				$list = array_values(array_filter($list, fn($item) => $item !== null));
			}

			$type?->__invoke(...$list);
		}
	}
}
