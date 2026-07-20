<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function is_string, sprintf;


/**
 * Utility functions for working with AST nodes.
 */
final class NodeHelpers
{
	/**
	 * Finds all nodes matching the filter and returns them in traversal order.
	 *
	 * The filter is either a node class or a predicate. Passing a class narrows the
	 * return type to that class, so the caller does not have to annotate it.
	 * @template T of Node
	 * @param  class-string<T>|callable(Node): bool  $filter
	 * @return ($filter is class-string ? list<T> : list<Node>)
	 */
	public static function find(Node $node, string|callable $filter): array
	{
		$predicate = self::toPredicate($filter);
		$found = [];
		(new NodeTraverser)
			->traverse($node, enter: function (Node $node) use ($predicate, &$found) {
				if ($predicate($node)) {
					$found[] = $node;
				}

				return null;
			});
		return $found;
	}


	/**
	 * Finds the first node matching the filter, or null if none.
	 * @template T of Node
	 * @param  class-string<T>|callable(Node): bool  $filter
	 * @return ($filter is class-string ? T|null : Node|null)
	 */
	public static function findFirst(Node $node, string|callable $filter): ?Node
	{
		$predicate = self::toPredicate($filter);
		$found = null;
		(new NodeTraverser)
			->traverse($node, enter: function (Node $node) use ($predicate, &$found) {
				if ($predicate($node)) {
					$found = $node;
					return NodeTraverser::StopTraversal;
				}

				return null;
			});
		return $found;
	}


	/**
	 * @param  class-string<Node>|callable(Node): bool  $filter
	 * @return callable(Node): bool
	 */
	private static function toPredicate(string|callable $filter): callable
	{
		if (!is_string($filter)) {
			return $filter;
		}

		if (!is_a($filter, Node::class, allow_string: true)) {
			throw new \LogicException(sprintf('Filter must be a %s subclass, %s given.', Node::class, $filter));
		}

		return static fn(Node $node): bool => $node instanceof $filter;
	}


	/**
	 * Creates a deep clone of the node tree.
	 */
	public static function clone(Node $node): Node
	{
		return (new NodeTraverser)
			->traverse($node, enter: fn(Node $node) => clone $node) ?? throw new \LogicException;
	}
}
