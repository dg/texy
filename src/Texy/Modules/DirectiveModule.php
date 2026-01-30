<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\DirectiveNode;
use Texy\ParseContext;
use Texy\Position;
use Texy\Syntax;
use function strlen;


/**
 * Processes {{macro}} script commands.
 */
final class DirectiveModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches, array $offsets) => trim($matches[1]) === ''
					? null
					: new DirectiveNode($matches[1], new Position($offsets[0], strlen($matches[0]))),
			'~
				\{\{
				((?:
					[^' . Texy\Patterns::MARK . '}]++ |  # content not containing }
					}                                    # or single }
				)+)
				}}
			~U',
			Syntax::Directive,
		);
	}
}
