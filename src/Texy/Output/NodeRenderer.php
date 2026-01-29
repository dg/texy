<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output;

use Texy\Node;
use Texy\Nodes;
use function array_key_exists, sprintf;


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

	/** @var array<class-string<Node>, ?\Closure(Node, self<TResult>): TResult>  lookups incl. inherited ones */
	private array $resolved = [];


	/**
	 * Registers how a node class is rendered; the class is taken from the type of the
	 * first parameter. Renderers for one class form a chain, called from the last
	 * registered to the first, with the built-in one at the end. Return null to pass
	 * the node on to the previous renderer.
	 *
	 * The signature is deliberately left open: a renderer narrows the first parameter
	 * to the node class it serves, which is the whole mechanism here, and any concrete
	 * callable type would be contravariantly wider and reject every real renderer.
	 */
	public function registerHandler(callable $renderer): void
	{
		$renderer = $renderer(...);
		$nodeClass = self::renderedClass($renderer);
		$previous = $this->findRenderer($nodeClass);
		$this->handlers[$nodeClass] = static function (Node $node, self $self) use ($renderer, $previous): mixed {
			$result = $renderer($node, $self, $previous);
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No renderer for node class ' . $node::class);
			}
			return $previous($node, $self);
		};
		$this->resolved = [];
	}


	/**
	 * Renders a single node via its registered renderer.
	 * @return TResult
	 */
	public function renderNode(Node $node): mixed
	{
		// exact match first - the common case must not pay for the inheritance walk
		$renderer = $this->handlers[$node::class] ?? $this->findRenderer($node::class);
		return $renderer
			? $renderer($node, $this)
			: $this->renderUnknown($node);
	}


	/**
	 * Renders a node this format has no renderer for. An extension may add node classes
	 * that only one format knows; degrading to their content keeps the document
	 * renderable everywhere instead of failing the whole render.
	 * @return TResult
	 */
	abstract protected function renderUnknown(Node $node): mixed;


	/**
	 * Renders the node's children, for renderUnknown() implementations.
	 * @return list<TResult>
	 */
	protected function renderChildrenOf(Node $node): array
	{
		$result = [];
		foreach ($node->getChildren() as $child) {
			$result[] = $this->renderNode($child);
		}

		return $result;
	}


	/**
	 * Reads the node class a renderer is registered for. A type that could never be
	 * dispatched to is refused here rather than silently never firing.
	 * @param  \Closure  $renderer  fn(TNode, static, ?\Closure): mixed
	 * @return class-string<Node>
	 */
	public static function renderedClass(\Closure $renderer): string
	{
		$params = (new \ReflectionFunction($renderer))->getParameters();
		$type = $params === [] ? null : $params[0]->getType();
		$name = $type instanceof \ReflectionNamedType && !$type->allowsNull()
			? $type->getName()
			: null;

		if ($name === null || !is_a($name, Node::class, allow_string: true)) {
			throw new \LogicException(sprintf(
				"Renderer's first parameter must be typed as a non-nullable %s subclass, %s given.",
				Node::class,
				match (true) {
					$params === [] => 'no parameter',
					$type === null => 'no type',
					default => (string) $type,
				},
			));
		}

		return $name;
	}


	/**
	 * Finds the renderer for a node class, falling back to ones registered for its
	 * ancestors, so that a subclass of a built-in node renders without its own handler.
	 * @param  class-string<Node>  $class
	 * @return ?\Closure(Node, self<TResult>): TResult
	 */
	private function findRenderer(string $class): ?\Closure
	{
		if (array_key_exists($class, $this->resolved)) {
			return $this->resolved[$class];
		}

		$handler = $this->handlers[$class] ?? null;
		foreach ($handler === null ? (class_parents($class) ?: []) : [] as $parent) {
			if (isset($this->handlers[$parent])) {
				$handler = $this->handlers[$parent];
				break;
			}
		}

		return $this->resolved[$class] = $handler;
	}


	/**
	 * Render document AST to final output string.
	 */
	abstract public function render(Nodes\DocumentNode $document): string;
}
