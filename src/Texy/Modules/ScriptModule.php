<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Regexp;


/**
 * Scripts module.
 */
final class ScriptModule extends Texy\Module
{
	/** arguments separator */
	public string $separator = ',';


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('script', $this->toElement(...));
		$texy->addHandler(Texy\Nodes\ScriptNode::class, $this->toElement(...));

		$texy->registerLinePattern(
			$this->parse(...),
			'~
				\{\{
				((?:
					[^' . Texy\Patterns::MARK . '}]++ |  # content not containing }
					}                                    # or single }
				)+)
				}}
			~U',
			'script',
		);
	}


	/**
	 * Callback for: {{...}}.
	 */
	public function parse(Texy\LineParser $parser, array $matches): Texy\HtmlElement|string|null
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
		if ($matches = Regexp::match($cmd, '~^ ([a-z_][a-z0-9_-]*) \s* (?: \( ([^()]*) \) | : (.*) )$~i')) {
			$cmd = $matches[1];
			$raw = trim($matches[3] ?? $matches[2]);
			if ($raw !== '') {
				$args = Regexp::split($raw, '~\s*' . Regexp::quote($this->separator) . '\s*~');
			}
		}

		return $this->texy->invokeAroundHandlers('script', $parser, [$cmd, $args, $raw]);
	}


	public function toElement(
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
