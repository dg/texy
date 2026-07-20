<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\Output\Html;
use Texy\ParseContext;


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
		$texy->htmlOutput->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			fn(ParseContext $context, array $matches) => new HorizontalRuleNode(
				((string) $matches[1])[0],
				Texy\Modifier::parse($matches[2]),
			),
			'~^
				( \*{3,}+ | -{3,}+ )         # three or more * or - (1)
				[ \t]*                       # optional spaces
				' . Texy\Patterns::MODIFIER . '? # modifier (2)
			$~mUx',
			'horizline',
		);
	}


	public function solve(HorizontalRuleNode $node, Html\Renderer $generator): Html\Element
	{
		$el = new Html\Element('hr');
		$node->modifier?->decorate($this->texy, $el);

		// Add default class if not already set via modifier
		$class = $this->classes[$node->type] ?? null;
		if ($class && empty($node->modifier?->classes[$class])) {
			settype($el->attrs['class'], 'array');
			$el->attrs['class'][] = $class;
		}

		return $el;
	}
}
