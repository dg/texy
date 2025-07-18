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
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler($this->processDirectives(...));
	}


	/**
	 * Consumes {{texy: ...}} directives: stores document-level options into
	 * DocumentNode::$meta and removes the directive nodes from the AST, so all
	 * output generators see the same document.
	 */
	public function processDirectives(Texy\Nodes\DocumentNode $doc): void
	{
		(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use ($doc): ?int {
			if ($node instanceof DirectiveNode) {
				$parsed = $node->parseContent();
				if ($parsed['name'] === 'texy' && $parsed['args']) {
					foreach ($parsed['args'] as $arg) {
						$doc->meta[$arg] = true;
					}
					return Texy\NodeTraverser::RemoveNode;
				}
			}
			return null;
		});
	}


	public function beforeParse(): void
	{
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches) => trim((string) $matches[1]) === '' ? null : new DirectiveNode((string) $matches[1]),
			'~
				\{\{
				((?:
					[^' . Texy\Patterns::MARK . '}]++ |  # content not containing }
					}                                    # or single }
				)+)
				}}
			~Ux',
			Syntax::Directive,
		);
	}
}
