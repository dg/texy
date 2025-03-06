<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\Output\Html;
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
		$texy->htmlOutput->addRenderer($this->render(...));
	}


	public function beforeParse(): void
	{
		$this->texy->addInline(
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


	public function render(DirectiveNode $node, Html\Renderer $renderer): string
	{
		// {{texy: ...}} sets options of the processing and leaves nothing in the output
		if ($node->name === 'texy' && $node->args) {
			if (in_array('nofollow', $node->args, true)) {
				$this->texy->linkModule->forceNoFollow = true;
			}
			return '';
		}

		// Unknown directives - preserve original text (entities decoded like any display text)
		return Texy\Helpers::decodeEntities('{{' . $node->text . '}}');
	}
}
