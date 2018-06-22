<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Horizontal line module.
 */
final class HorizLineModule extends Texy\Module
{
	/** @var array  default CSS class */
	public $classes = [
		'-' => null,
		'*' => null,
	];


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('horizline', [$this, 'solve']);

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^(\*{3,}+|-{3,}+)\ *' . Texy\Patterns::MODIFIER . '?()$#mU',
			'horizline'
		);
	}


	/**
	 * Callback for: -------.
	 * @return Texy\HtmlElement
	 */
	public function pattern(Texy\BlockParser $parser, array $matches)
	{
		list(, $mType, $mMod) = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		$mod = new Texy\Modifier($mMod);
		return $this->texy->invokeAroundHandlers('horizline', $parser, [$mType, $mod]);
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement
	 */
	public function solve(Texy\HandlerInvocation $invocation, $type, Texy\Modifier $modifier)
	{
		$el = new Texy\HtmlElement('hr');
		$modifier->decorate($invocation->getTexy(), $el);

		$class = $this->classes[$type[0]];
		if ($class && !isset($modifier->classes[$class])) {
			$el->attrs['class'][] = $class;
		}

		return $el;
	}
}
