<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\Node;
use Texy\Nodes;
use Texy\Texy;
use function implode, is_string;


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
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $node, self $generator) => $generator->renderDocument($node),
			Nodes\TextNode::class => fn(Nodes\TextNode $node, self $generator) => $node->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $node, self $generator) => $generator->serialize($generator->renderNodes($node->children)),
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
		assert(is_string($s), 'DocumentNode handler must return string');
		return $this->texy->stringToHtml($s);
	}


	/**
	 * Register a handler for a node class.
	 * Return null to delegate to previous handler.
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
			if ($child instanceof Element) {
				$html[] = $child->toString($this->texy);
			} else {
				$html[] = $child;
			}
		}
		return implode($separator, $html);
	}


	private function renderDocument(Nodes\DocumentNode $node): string
	{
		return $this->serialize($this->renderNodes($node->content->children), '');
	}
}
