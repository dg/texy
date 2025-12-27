<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use function preg_quote, preg_split, trim;


/**
 * Processes {{macro}} script commands.
 */
final class ScriptModule extends Texy\Module
{
	/** arguments separator */
	public string $separator = ',';


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('script', $this->solve(...));

		$texy->registerLinePattern(
			$this->pattern(...),
			'#\{\{((?:[^' . Texy\Patterns::MARK . '}]++|[}])+)\}\}()#U',
			'script',
		);
	}


	/**
	 * Callback for: {{...}}.
	 * @param  string[]  $matches
	 */
	public function pattern(Texy\LineParser $parser, array $matches): Texy\HtmlElement|string|null
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
	 * @param ?list<string>  $args
	 */
	private function solve(
		Texy\HandlerInvocation $invocation,
		string $cmd,
		?array $args = null,
		?string $raw = null,
	): Texy\HtmlElement|string|null
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
