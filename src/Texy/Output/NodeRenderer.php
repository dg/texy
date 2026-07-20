<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output;

use Texy\Node;
use Texy\Nodes;


/**
 * Base for AST output generators: dispatch by node class with extensible,
 * chainable handlers. Serialization, escaping and configuration are
 * format-specific and belong to subclasses.
 * @template TResult
 */
abstract class NodeRenderer
{
	/** @var array<class-string<Node>, \Closure(Node, self<TResult>): TResult> */
	protected array $handlers = [];


	/**
	 * Register a handler for a node class (determined by the type of its first parameter).
	 * Return null to delegate to the previous handler.
	 * @param \Closure(Node, self<TResult>, ?\Closure): ?TResult $handler
	 */
	public function registerHandler(\Closure $handler): void
	{
		/** @var class-string<Node> $nodeClass */
		$nodeClass = (string) (new \ReflectionFunction($handler))->getParameters()[0]->getType();
		$previous = $this->handlers[$nodeClass] ?? null;
		$this->handlers[$nodeClass] = static function (Node $node, self $renderer) use ($handler, $previous): mixed {
			$result = $handler($node, $renderer, $previous);
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No handler for node class ' . $node::class);
			}
			return $previous($node, $renderer);
		};
	}


	/**
	 * Render a single node via the registered handler.
	 * @return TResult
	 */
	public function renderNode(Node $node): mixed
	{
		$handler = $this->handlers[$node::class]
			?? throw new \LogicException('No handler for node class ' . $node::class);
		return $handler($node, $this);
	}


	/**
	 * Render document AST to final output string.
	 */
	abstract public function render(Nodes\DocumentNode $document): string;
}
