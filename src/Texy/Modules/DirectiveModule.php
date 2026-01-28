<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Output\Html;
use Texy\ParseContext;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches) => trim($matches[1]) === ''
					? null
					: new DirectiveNode($matches[1]),
			'~
				\{\{
				((?:
					[^' . Texy\Patterns::MARK . '}]++ |  # content not containing }
					}                                    # or single }
				)+)
				}}
			~U',
			'script',
		);
	}


	public function solve(DirectiveNode $node, Html\Generator $generator): string
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
		return '{{' . $node->content . '}}';
	}
}
