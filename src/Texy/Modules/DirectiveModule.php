<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function strlen, trim;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler('afterParse', $this->processDirectives(...));
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


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
			$this->parse(...),
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
