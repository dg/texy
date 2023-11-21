<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;


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

		$texy->addHandler('horizline', $this->solve(...));

		$texy->registerBlockPattern(
			$this->pattern(...),
			'#^(\*{3,}+|-{3,}+)[\ \t]*' . Texy\Patterns::MODIFIER . '?()$#mU',
			'horizline',
		);
	}


	/**
	 * Callback for: -------.
	 */
	public function pattern(Texy\BlockParser $parser, array $matches): Texy\HtmlElement
	{
		[, $mType, $mMod] = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		$mod = new Texy\Modifier($mMod);
		return $this->texy->invokeAroundHandlers('horizline', $parser, [$mType, $mod]);
	}


	/**
	 * Finish invocation.
	 */
	private function solve(Texy\HandlerInvocation $invocation, string $type, Texy\Modifier $modifier): Texy\HtmlElement
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
