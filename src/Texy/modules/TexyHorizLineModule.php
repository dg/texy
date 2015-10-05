<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Horizontal line module.
 */
final class TexyHorizLineModule extends TexyModule
{
	/** @var array  default CSS class */
	public $classes = [
		'-' => NULL,
		'*' => NULL,
	];


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->addHandler('horizline', [$this, 'solve']);

		$texy->registerBlockPattern(
			[$this, 'pattern'],
			'#^(\*{3,}+|-{3,}+)\ *'.TexyPatterns::MODIFIER.'?()$#mU',
			'horizline'
		);
	}


	/**
	 * Callback for: -------.
	 *
	 * @param  TexyBlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return TexyHtml
	 */
	public function pattern($parser, $matches)
	{
		list(, $mType, $mMod) = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		$mod = new TexyModifier($mMod);
		return $this->texy->invokeAroundHandlers('horizline', $parser, [$mType, $mod]);
	}


	/**
	 * Finish invocation.
	 *
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  TexyModifier
	 * @return TexyHtml
	 */
	public function solve($invocation, $type, $modifier)
	{
		$el = TexyHtml::el('hr');
		$modifier->decorate($invocation->getTexy(), $el);

		$class = $this->classes[ $type[0] ];
		if ($class && !isset($modifier->classes[$class])) {
			$el->attrs['class'][] = $class;
		}

		return $el;
	}

}
