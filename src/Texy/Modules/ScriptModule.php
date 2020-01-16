<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;


/**
 * Scripts module.
 */
final class ScriptModule extends Texy\Module
{
	/** @var string  arguments separator */
	public $separator = ',';


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('script', [$this, 'solve']);

		$texy->registerLinePattern(
			[$this, 'pattern'],
			'#\{\{((?:[^' . Texy\Patterns::MARK . '}]++|[}])+)\}\}()#U',
			'script'
		);
	}


	/**
	 * Callback for: {{...}}.
	 *
	 * @return Texy\HtmlElement|string|null
	 */
	public function pattern(Texy\LineParser $parser, array $matches)
	{
		[, $mContent] = $matches;
		// [1] => ...

		$cmd = trim($mContent);
		if ($cmd === '') {
			return null;
		}

		$raw = null;
		$args = [];
		// function (arg, arg, ...) or function: arg, arg
		if ($matches = Texy\Regexp::match($cmd, '#^([a-z_][a-z0-9_-]*)\s*(?:\(([^()]*)\)|:(.*))$#iu')) {
			$cmd = $matches[1];
			$raw = trim($matches[3] ?? $matches[2]);
			if ($raw !== '') {
				$args = preg_split('#\s*' . preg_quote($this->separator, '#') . '\s*#u', $raw);
			}
		}

		return $this->texy->invokeAroundHandlers('script', $parser, [$cmd, $args, $raw]);
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|string|null
	 */
	public function solve(Texy\HandlerInvocation $invocation, string $cmd, array $args = null, string $raw = null)
	{
		if ($cmd === 'texy' && $args) {
			switch ($args[0]) {
				case 'nofollow':
					$this->texy->linkModule->forceNoFollow = true;
					break;
			}
			return '';
		}
		return null;
	}
}
