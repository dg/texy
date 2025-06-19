<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{Node, Nodes, Texy};
use function is_string;


/**
 * Renders the AST to the output HTML string. Created per render() call;
 * configuration is read from the Config object.
 */
final class Renderer
{
	/** @var array<string, \Closure> */
	private array $handlers = [];


	public function __construct(
		public readonly Config $config,
		private Texy $texy,
	) {
		$this->handlers = [
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->serialize($g->renderNodes($n->children)),
		];

		foreach ($config->getHandlers() as $handler) {
			$this->registerHandler($handler);
		}
	}


	/**
	 * Render document AST to final HTML string.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$s = $this->renderNode($document);
		assert(is_string($s));
		return $this->texy->stringToHtml($s);
	}


	/**
	 * Register a handler for a node class.
	 * Return null to delegate to previous handler.
	 * @param \Closure(Node, self, ?\Closure): (Element|string|null) $handler
	 */
	public function registerHandler(\Closure $handler): void
	{
		/** @var class-string<Node> $nodeClass */
		$nodeClass = (string) (new \ReflectionFunction($handler))->getParameters()[0]->getType();
		$previous = $this->handlers[$nodeClass] ?? null;
		$this->handlers[$nodeClass] = static function (Node $node, self $gen) use ($handler, $previous): Element|string {
			$result = $handler($node, $gen, $previous);
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No handler for node class ' . $node::class);
			}
			return $previous($node, $gen);
		};
	}


	/**
	 * Render node to HTML Element or string.
	 */
	public function renderNode(Node $node): Element|string
	{
		$handler = $this->handlers[$node::class]
			?? throw new \LogicException('No handler for node class ' . $node::class);
		return $handler($node, $this);
	}


	/**
	 * @param array<Node> $content
	 * @return list<Element|string>
	 */
	public function renderNodes(array $content): array
	{
		$result = [];
		foreach ($content as $child) {
			$result[] = $this->renderNode($child);
		}
		return $result;
	}


	/**
	 * Serialize Element/string array to string.
	 * @param array<Element|string> $content
	 */
	public function serialize(array $content, string $separator = ''): string
	{
		$html = [];
		foreach ($content as $child) {
			$html[] = $child instanceof Element ? $child->toString($this->texy) : $child;
		}
		return implode($separator, $html);
	}


	private function renderDocument(Nodes\DocumentNode $node): string
	{
		return $this->serialize($this->renderNodes($node->content->children));
	}
}
