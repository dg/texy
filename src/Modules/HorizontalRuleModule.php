<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;
use Texy\Output\Html;
use Texy\Parsing\ParseContext;
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
		$texy->htmlOutput->addRenderer($this->render(...));
	}


	public function beforeParse(): void
	{
		$this->texy->addBlock(
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


	public function render(HorizontalRuleNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('hr');
		$renderer->decorateElement($node->modifier, $el);

		// Add default class if not already set via modifier
		$class = $this->classes[$node->type] ?? null;
		if ($class && empty($node->modifier?->classes[$class])) {
			$el->addClass($class);
		}

		return $el;
	}
}
