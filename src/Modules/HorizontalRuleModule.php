<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\Parsing\ParseContext;
use Texy\Syntax;


/**
 * Processes horizontal line syntax (---, ***).
 */
final class HorizontalRuleModule extends Texy\Module
{
	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->addBlock(
			Syntax::HorizontalRule,
			'~^
				( \*{3,}+ | -{3,}+ )         # three or more * or - (1)
				[ \t]*                       # optional spaces
				' . Texy\Patterns::MODIFIER . '? # modifier (2)
			$~mUx',
			fn(ParseContext $context, array $matches) => new HorizontalRuleNode(
				((string) $matches[1])[0],
				Texy\Modifier::parse($matches[2]),
			),
		);
	}
}
