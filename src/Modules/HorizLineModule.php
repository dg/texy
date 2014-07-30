<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Horizontal line module.
 *
 * @author     David Grudl
 */
final class HorizLineModule extends Texy\Module
{
	/** @var array  default CSS class */
	public $classes = array(
		'-' => NULL,
		'*' => NULL,
	);


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('horizline', array($this, 'solve'));

		$texy->registerBlockPattern(
			array($this, 'pattern'),
			'#^(\*{3,}+|-{3,}+)\ *'.Texy\Patterns::MODIFIER.'?()$#mU',
			'horizline'
		);
	}


	/**
	 * Callback for: -------.
	 *
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement
	 */
	public function pattern($parser, $matches)
	{
		list(, $mType, $mMod) = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		$mod = new Texy\Modifier($mMod);
		return $this->texy->invokeAroundHandlers('horizline', $parser, array($mType, $mod));
	}


	/**
	 * Finish invocation.
	 *
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement
	 */
	public function solve($invocation, $type, $modifier)
	{
		$el = Texy\HtmlElement::el('hr');
		$modifier->decorate($invocation->texy, $el);

		$class = $this->classes[ $type[0] ];
		if ($class && !isset($modifier->classes[$class])) {
			$el->attrs['class'][] = $class;
		}

		return $el;
	}

}
