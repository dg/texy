<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Parsing\ParseContext;
use Texy\Syntax;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	/**
	 * Consumes {{texy: ...}} directives: stores document-level options into
	 * DocumentNode::$meta and removes the directive nodes from the AST, so all
	 * output generators see the same document.
	 */
	public function processDirectives(Texy\Nodes\DocumentNode $doc): void
	{
		(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use ($doc): ?Texy\TraverseAction {
			if (!$node instanceof DirectiveNode) {
				return null;
			}

			if ($node->name === 'texy' && $node->args) {
				foreach ($node->args as $arg) {
					$doc->meta[$arg] = true;
				}
				return Texy\TraverseAction::Remove;
			}

			return null;
		});
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->addInline(
			Syntax::Directive,
			'~
				\{\{
				((?:
					[^' . Texy\Patterns::MARK . '}]++ |  # content not containing }
					}                                    # or single }
				)+)
				}}
			~Ux',
			fn(ParseContext $context, array $matches) => trim((string) $matches[1]) === '' ? null : new DirectiveNode((string) $matches[1]),
		);
	}
}
