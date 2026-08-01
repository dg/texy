<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Parsing\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function strlen;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	/** @var array<string, \Closure(?string, list<string>, DirectiveNode): ?Texy\Node> */
	private array $handlers = [];

	/** @var ?\Closure(?string, list<string>, DirectiveNode): ?Texy\Node */
	private ?\Closure $fallback = null;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addPass($this->processDirectives(...));
	}


	/** @param  callable(?string, list<string>, DirectiveNode): ?Texy\Node  $handler */
	public function addDirective(string $name, callable $handler): void
	{
		$this->handlers[Texy\Helpers::toLower($name)] = $handler(...);
	}


	/**
	 * Registers a handler for directives that have no named handler.
	 *
	 * Without it an unhandled directive stays in the document and renders as the author
	 * wrote it, and the consumer has no way of learning about it. The handler receives
	 * the same arguments as a named one, so the name is read from the node.
	 * @param  ?callable(?string, list<string>, DirectiveNode): ?Texy\Node  $handler
	 */
	public function setFallback(?callable $handler): void
	{
		$this->fallback = $handler === null ? null : $handler(...);
	}


	/**
	 * Consumes {{texy: ...}} into DocumentNode::$meta and hands the rest to registered
	 * handlers. Runs as the first pass, so a handler may still change configuration the
	 * later ones depend on. A handled directive is removed unless the handler returns
	 * a replacement.
	 */
	public function processDirectives(Texy\Nodes\DocumentNode $doc): void
	{
		(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use ($doc): Texy\Node|int|null {
			if (!$node instanceof DirectiveNode) {
				return null;
			}

			$parsed = $node->parseContent();
			if ($parsed['name'] === 'texy' && $parsed['args']) {
				foreach ($parsed['args'] as $arg) {
					$doc->meta[$arg] = true;
				}
				return Texy\NodeTraverser::RemoveNode;
			}

			$handler = $this->handlers[Texy\Helpers::toLower($parsed['name'])] ?? $this->fallback;
			return $handler === null
				? null
				: $handler($parsed['value'], $parsed['args'], $node) ?? Texy\NodeTraverser::RemoveNode;
		});
	}


	public function beforeParse(): void
	{
		$this->texy->addLineSyntax(
			Syntax::Directive,
			'~
				\{\{
				((?:
					[^}]++ |   # content not containing }
					}          # or single }
				)+)
				}}
			~Ux',
			$this->parse(...),
		);
	}


	/**
	 * Parses {{macro}}.
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?DirectiveNode
	{
		return trim($matches[1]) === ''
			? null
			: new DirectiveNode($matches[1], new Range($offsets[0], strlen($matches[0])));
	}
}
