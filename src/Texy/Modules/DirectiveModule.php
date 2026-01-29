<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Syntax;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlOutput->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
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


	public function solve(DirectiveNode $node, Html\Renderer $generator): string
	{
		$parsed = $node->parseContent();

		// Handle special directives
		if ($parsed['name'] === 'texy' && $parsed['args']) {
			switch ($parsed['args'][0]) {
				case 'nofollow':
					$this->texy->linkModule->forceNoFollow = true;
					break;
			}
			// texy directive with args returns empty
			return '';
		}

		// Unknown directives - preserve original text
		return '{{' . $node->text . '}}';
	}
}
