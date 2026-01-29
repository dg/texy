<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Syntax;


/**
 * Processes horizontal line syntax (---, ***).
 */
final class HorizontalRuleModule extends Texy\Module
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


	public function solve(HorizontalRuleNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element('hr');
		$node->modifier?->decorate($this->texy, $el);

		// Add default class if not already set via modifier
		$class = $this->classes[$node->type] ?? null;
		if ($class && empty($node->modifier?->classes[$class])) {
			$el->attrs['class'] = (array) ($el->attrs['class'] ?? []);
			$el->attrs['class'][] = $class;
		}

		return $el;
	}
}
