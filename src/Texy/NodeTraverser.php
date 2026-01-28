<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Traverses and transforms AST nodes using visitor pattern.
 */
final class NodeTraverser
{
	public const DontTraverseChildren = 1;
	public const StopTraversal = 2;
	public const RemoveNode = 3;

	/** @var ?(\Closure(Node): (Node|int|null)) */
	private ?\Closure $enter = null;

	/** @var ?(\Closure(Node): (Node|int|null)) */
	private ?\Closure $leave = null;

	private bool $stop;


	/**
	 * @param (\Closure(Node): (Node|int|null))|null $enter
	 * @param (\Closure(Node): (Node|int|null))|null $leave
	 */
	public function traverse(Node $node, ?\Closure $enter = null, ?\Closure $leave = null): ?Node
	{
		$this->enter = $enter;
		$this->leave = $leave;
		$this->stop = false;
		return $this->traverseNode($node);
	}


	private function traverseNode(Node $node): ?Node
	{
		$children = true;
		if ($this->enter) {
			$res = ($this->enter)($node);
			if ($res instanceof Node) {
				$node = $res;

			} elseif ($res === self::DontTraverseChildren) {
				$children = false;

			} elseif ($res === self::StopTraversal) {
				$this->stop = true;
				return $node;

			} elseif ($res === self::RemoveNode) {
				return new Nodes\TextNode(''); // TODO: return null
			}
		}

		if ($children) {
			foreach ($node->getNodes() as &$subnode) {
				$subnode = $this->traverseNode($subnode);
				if ($this->stop) {
					break;
				}
			}
		}

		if (!$this->stop && $this->leave) {
			$res = ($this->leave)($node);
			if ($res instanceof Node) {
				$node = $res;

			} elseif ($res === self::StopTraversal) {
				$this->stop = true;

			} elseif ($res === self::RemoveNode) {
				return null;
			}
		}

		return $node;
	}
}
