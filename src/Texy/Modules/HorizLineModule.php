<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Position;
use function strlen;


/**
 * Processes horizontal line syntax (---, ***).
 */
final class HorizLineModule extends Texy\Module
{
	/** @var array<string, ?string>  default CSS class */
	public array $classes = [
		'-' => null,
		'*' => null,
	];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				( \*{3,}+ | -{3,}+ )         # three or more * or - (1)
				[ \t]*                       # optional spaces
				' . Texy\Patterns::MODIFIER . '? # modifier (2)
			$~mU',
			'horizline',
		);
	}


	/**
	 * Parses -------
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(
		Texy\BlockParser $parser,
		array $matches,
		string $name,
		array $offsets,
	): Texy\Nodes\HorizontalRuleNode
	{
		[, $mType, $mMod] = $matches;
		return new Texy\Nodes\HorizontalRuleNode(
			$mType[0],
			Texy\Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	public function solve(Nodes\HorizontalRuleNode $node, Generator $generator): string
	{
		$attrs = $generator->generateModifierAttrs($node->modifier);
		return $this->texy->protect("<hr{$attrs}>", $this->texy::CONTENT_REPLACED);
	}
}
