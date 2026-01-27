<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Regexp;
use function trim;


/**
 * Processes {{macro}} script commands.
 */
final class ScriptModule extends Texy\Module
{
	/** arguments separator */
	public string $separator = ',';


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler('script', $this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
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
	 * Parses {{...}}
	 * @param  array<?string>  $matches
	 */
	public function parse(Texy\InlineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		/** @var array{string, string} $matches */
		[, $mContent] = $matches;
		// [1] => ...

		$cmd = trim($mContent);
		if ($cmd === '') {
			return null;
		}

		$raw = null;
		$args = [];
		// function (arg, arg, ...) or function: arg, arg
		/** @var array{string, string, ?string, ?string} $matches */
		if ($matches = Regexp::match($cmd, '~^ ([a-z_][a-z0-9_-]*) \s* (?: \( ([^()]*) \) | : (.*) )$~i')) {
			$cmd = $matches[1];
			$raw = trim((string) ($matches[3] ?? $matches[2]));
			if ($raw !== '') {
				$args = Regexp::split($raw, '~\s*' . Regexp::quote($this->separator) . '\s*~');
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
	): ?string
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
