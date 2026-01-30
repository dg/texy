<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\ParseContext;
use Texy\Syntax;


/**
 * Processes horizontal line syntax (---, ***).
 */
final class HorizontalRuleModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			fn(ParseContext $context, array $matches) => new HorizontalRuleNode(
				$matches[1][0],
				Texy\Modifier::parse($matches[2]),
			),
			'~^
				( \*{3,}+ | -{3,}+ )         # three or more * or - (1)
				[ \t]*                       # optional spaces
				' . Texy\Patterns::MODIFIER . '? # modifier (2)
			$~mU',
			Syntax::HorizontalRule,
		);
	}
}
