<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function strlen;


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
			$this->parse(...),
			'~^
				( \*{3,}+ | -{3,}+ )         # three or more * or - (1)
				[ \t]*                       # optional spaces
				' . Texy\Patterns::Modifier . '? # modifier (2)
			$~mUx',
			Syntax::HorizontalRule,
		);
	}


	/**
	 * Parses --- and ***.
	 * @param  array{string, string, ?string}  $matches
	 * @param  array{int, int, ?int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): HorizontalRuleNode
	{
		return new HorizontalRuleNode(
			$matches[1][0],
			Texy\Modifier::parse($matches[2], $offsets[2]),
			new Range($offsets[0], strlen($matches[0])),
		);
	}
}
