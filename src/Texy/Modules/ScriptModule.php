<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Scripts module.
 */
final class ScriptModule extends Texy\Module
{
	/**
	 * @var callback|object  script elements handler
	 * function myFunc($parser, $cmd, $args, $raw)
	 */
	public $handler;


	/** @var string  arguments separator */
	public $separator = ',';


	public function __construct($texy)
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
	 * @return Texy\HtmlElement|string|false
	 */
	public function pattern(Texy\LineParser $parser, array $matches)
	{
		list(, $mContent) = $matches;
		// [1] => ...

		$cmd = trim($mContent);
		if ($cmd === '') {
			return false;
		}

		$raw = null;
		$args = [];
		// function (arg, arg, ...) or function: arg, arg
		if ($matches = Texy\Regexp::match($cmd, '#^([a-z_][a-z0-9_-]*)\s*(?:\(([^()]*)\)|:(.*))$#iu')) {
			$cmd = $matches[1];
			$raw = isset($matches[3]) ? trim($matches[3]) : trim($matches[2]);
			if ($raw !== '') {
				$args = preg_split('#\s*' . preg_quote($this->separator, '#') . '\s*#u', $raw);
			}
		}

		// Texy 1.x way
		if ($this->handler) {
			if (is_callable([$this->handler, $cmd])) {
				array_unshift($args, $parser);
				return call_user_func_array([$this->handler, $cmd], $args);
			}

			if (is_callable($this->handler)) {
				return call_user_func_array($this->handler, [$parser, $cmd, $args, $raw]);
			}
		}

		// Texy 2 way
		return $this->texy->invokeAroundHandlers('script', $parser, [$cmd, $args, $raw]);
	}


	/**
	 * Finish invocation.
	 * @return Texy\HtmlElement|string|false
	 */
	public function solve(Texy\HandlerInvocation $invocation, $cmd, array $args = null, $raw)
	{
		if ($cmd === 'texy') {
			if (!$args) {
				return false;
			}

			switch ($args[0]) {
				case 'nofollow':
					$this->texy->linkModule->forceNoFollow = true;
					break;
			}
			return '';

		} else {
			return false;
		}
	}
}
