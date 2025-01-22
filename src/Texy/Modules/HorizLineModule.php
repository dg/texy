<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HorizontalRuleNode;


/**
 * Horizontal line module.
 */
final class HorizLineModule extends Texy\Module
{
	/** @var array<string, ?string>  default CSS class */
	public array $classes = [
		'-' => null,
		'*' => null,
	];


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('horizline', $this->toElement(...));
		$texy->addHandler(HorizontalRuleNode::class, $this->toElement(...));

		$texy->registerBlockPattern(
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
	 * Callback for: -------.
	 */
	public function parse(Texy\BlockParser $parser, array $matches): HorizontalRuleNode
	{
		[, $mType, $mMod] = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		return new HorizontalRuleNode($mType, $mMod ? new Texy\Modifier($mMod) : null);
	}


	private function toElement(HorizontalRuleNode $node, Texy\Texy $texy): Texy\HtmlElement
	{
		$el = new Texy\HtmlElement('hr');
		$node->modifier?->decorate($texy, $el);

		$class = $this->classes[$node->type[0]];
		if ($class && !isset($modifier->classes[$class])) {
			$el->attrs['class'][] = $class;
		}

		return $el;
	}
}
