<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function sprintf;


/**
 * Traverses and transforms AST nodes using visitor pattern.
 */
final class NodeTraverser
{
	/** @var ?(\Closure(Node): (Node|TraverseAction|null)) */
	private ?\Closure $enter = null;

	/** @var ?(\Closure(Node): (Node|TraverseAction|null)) */
	private ?\Closure $leave = null;

	private bool $stop;


	/**
	 * @param (\Closure(Node): (Node|TraverseAction|null))|null $enter
	 * @param (\Closure(Node): (Node|TraverseAction|null))|null $leave
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

			} elseif ($res === TraverseAction::SkipChildren) {
				$children = false;

			} elseif ($res === TraverseAction::Stop) {
				$this->stop = true;
				return $node;

			} elseif ($res === TraverseAction::Remove) {
				return null;
			}
		}

		if ($children) {
			foreach ($node->getChildren() as &$subnode) {
				$res = $this->traverseNode($subnode);
				if ($res !== $subnode) {
					try {
						$subnode = $res;
					} catch (\TypeError $e) {
						throw new \LogicException(sprintf(
							'Cannot %s child node of %s during traversal, its slot does not allow that.',
							$res === null ? 'remove' : 'replace',
							$node::class,
						), 0, $e);
					}
				}
				if ($this->stop) {
					break;
				}
			}
		}

		if (!$this->stop && $this->leave) {
			$res = ($this->leave)($node);
			if ($res instanceof Node) {
				$node = $res;

			} elseif ($res === TraverseAction::Stop) {
				$this->stop = true;

			} elseif ($res === TraverseAction::Remove) {
				return null;
			}
		}

		return $node;
	}
}
