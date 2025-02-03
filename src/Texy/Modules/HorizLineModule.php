<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
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

		$texy->addHandler('horizline', $this->toElement(...));

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
	public function parse(Texy\BlockParser $parser, array $matches): Texy\HtmlElement
	{
		[, $mType, $mMod] = $matches;
		// [1] => ---
		// [2] => .(title)[class]{style}<>

		$mod = new Texy\Modifier($mMod);
		return $this->texy->invokeAroundHandlers('horizline', $parser, [$mType, $mod]);
	}


	public function toElement(Texy\HandlerInvocation $invocation, string $type, Texy\Modifier $modifier): Texy\HtmlElement
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
