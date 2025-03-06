<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;

use Texy\{Modifier, Node, Nodes, Texy};
use function count, is_string;


/**
 * Renders the AST to the output HTML string. The modules render their nodes by the
 * handlers they register on Config, the renderer dispatches to them and serializes
 * the result. Configuration is read from the Config object, so the instance is a
 * configuration snapshot reusable across documents.
 */
final class Renderer
{
	// types of protection marks
	public const
		ContentMarkup = "\x17",
		ContentReplaced = "\x16",
		ContentTextual = "\x15",
		ContentBlock = "\x14";

	/** @var array<string, \Closure> */
	private array $handlers;

	/** @var array<string, string>  Protection markup table */
	private array $marks = [];


	public function __construct(
		public readonly Config $config,
		private Texy $texy,
		/** @var ?\Closure(string): string typography for isolated strings (title, alt); null = disabled */
		private ?\Closure $postLine = null,
		/** @var list<\Closure(string): string> post-processing of the document text */
		private array $postHandlers = [],
	) {
		$this->handlers = [
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->serialize($g->renderNodes($n->content->children)),
			Nodes\TextNode::class => fn(Nodes\TextNode $n) => $n->text,
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->serialize($g->renderNodes($n->children)),
		];

		foreach ($config->getRenderers() as $handler) {
			$this->addRenderer($handler);
		}
	}


	/**
	 * Render document AST to final HTML string.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->marks = [];
		$s = $this->renderNode($document);
		assert(is_string($s));

		// line-postprocessing
		$blocks = explode(self::ContentBlock, $s);
		foreach ($this->postHandlers as $handler) {
			foreach ($blocks as $n => $s) {
				if ($n % 2 === 0 && $s !== '') {
					$blocks[$n] = $handler($s);
				}
			}
		}

		$s = implode(self::ContentBlock, $blocks);

		// encode < > &
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');

		// replace protected marks
		$s = $this->unprotect($s);

		// wellform and reformat HTML
		$wellFormer = new WellFormer($this->config);
		$wellFormer->raw($s);
		return ltrim($wellFormer->finish(), "\n");
	}


	/**
	 * Registers how a node class is rendered; the class is taken from the type of the first
	 * parameter. Handlers for one class form a chain, called from the last registered to the
	 * first. The handler gets the node, this renderer and $previous, which renders the node
	 * the way the previous one in the chain does: $previous($node). Returning null passes the
	 * node on to it.
	 */
	public function addRenderer(\Closure $handler): void
	{
		$nodeClass = (string) new \ReflectionFunction($handler)->getParameters()[0]->getType();
		$previous = $this->handlers[$nodeClass] ?? null;
		$this->handlers[$nodeClass] = static function (Node $node, self $self) use ($handler, $previous): Element|string {
			$result = $handler($node, $self, $previous === null ? null : fn(Node $node): Element|string => $previous($node, $self));
			if ($result !== null) {
				return $result;
			}
			if ($previous === null) {
				throw new \LogicException('No handler for node class ' . $node::class);
			}
			return $previous($node, $self);
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
	 * Render children as array (for Element->children).
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
			$html[] = $child instanceof Element ? $this->serializeElement($child) : $child;
		}

		return implode($separator, $html);
	}


	/**
	 * Serialize a single Element to string, its tags protected.
	 */
	private function serializeElement(Element $el): string
	{
		$ct = $this->getElementContentType($el);
		$s = $this->protect($el->startTag(), $ct);
		if ($el->isVoid()) {
			return $s;
		}

		foreach ($el->children as $child) {
			$s .= $child instanceof Element ? $this->serializeElement($child) : $child;
		}

		return $s . $this->protect($el->endTag(), $ct);
	}


	/**
	 * Determine content type for an element.
	 */
	private function getElementContentType(Element $el): string
	{
		$inlineType = Schema::inlineElements()[$el->name ?? ''] ?? null;
		return $inlineType === null
			? self::ContentBlock
			: ($inlineType ? self::ContentReplaced : self::ContentMarkup);
	}


	/**
	 * Applies typography to an isolated string (title, alt) unless typography is disabled.
	 * @internal
	 */
	public function postLineText(string $s): string
	{
		return $this->postLine !== null ? ($this->postLine)($s) : $s;
	}


	/********************* protection mechanism ****************d*g**/


	/**
	 * Generate unique mark - useful for freezing (folding) some substrings.
	 */
	public function protect(string $child, string $contentType): string
	{
		if ($child === '') {
			return '';
		}

		$key = $contentType
			. strtr(base_convert((string) count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
			. $contentType;

		$this->marks[$key] = $child;

		return $key;
	}


	/**
	 * Replace protection marks back to original content.
	 */
	public function unprotect(string $html): string
	{
		return strtr($html, $this->marks);
	}


	/********************* utility methods ****************d*g**/


	/**
	 * Wrap children in a null element (no tag wrapper).
	 * @param list<Element|string> $children
	 */
	public function wrapChildren(array $children): Element
	{
		$el = new Element;
		$el->children = $children;
		return $el;
	}


	/**
	 * Decorate element with modifier's classes, styles, and attributes.
	 */
	public function decorateElement(?Modifier $modifier, Element $el): void
	{
		$modifier?->decorate($this->texy, $el);
	}


	/**
	 * Applies the float of an image or a figure: its class from $leftClass or $rightClass of
	 * ImageModule, or the inline style.
	 * @param  'left'|'right'|null  $float
	 */
	public function applyFloat(Element $el, ?string $float): void
	{
		if ($float !== null) {
			$class = $float === 'left' ? $this->texy->imageModule->leftClass : $this->texy->imageModule->rightClass;
			Modifier::applyClassOrStyle($el->attrs, $class, 'float', $float);
		}
	}
}
