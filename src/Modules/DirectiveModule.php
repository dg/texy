<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Parsing\ParseContext;
use Texy\{Range, Syntax};
use function strlen;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	/**
	 * Consumes {{texy: ...}} into DocumentNode::$meta and hands the rest to registered
	 * handlers. Runs as the first pass, so a handler may still change configuration the
	 * later ones depend on. A handled directive is removed unless the handler returns
	 * a replacement.
	 */
	public function processDirectives(Texy\Nodes\DocumentNode $doc): void
	{
		(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use ($doc): Texy\Node|Texy\TraverseAction|null {
			if (!$node instanceof DirectiveNode) {
				return null;
			}

			if ($node->name === 'texy' && $node->args) {
				foreach ($node->args as $arg) {
					$doc->meta[$arg] = true;
				}
				return Texy\TraverseAction::Remove;
			}

			$handler = $this->config->directives[Texy\Helpers::toLower($node->name)] ?? $this->config->directiveFallback;
			if ($handler === null) {
				return null;
			}

			$result = $handler($node);
			return $result instanceof Texy\Node ? $result : Texy\TraverseAction::Remove;
		});
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->addInline(
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
