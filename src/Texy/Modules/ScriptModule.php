<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\InlineParser;
use Texy\Nodes\DirectiveNode;
use Texy\Output\Html\Generator;
use Texy\Position;
use Texy\Regexp;
use function strlen;


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
		$texy->htmlGenerator->registerHandler($this->solve(...));
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
	 * @param  array<?int>  $offsets
	 */
	public function parse(InlineParser $parser, array $matches, string $name, array $offsets): ?DirectiveNode
	{
		[, $mContent] = $matches;

		$cmd = trim($mContent);
		if ($cmd === '') {
			return null;
		}

		$raw = null;
		$args = [];
		// function (arg, arg, ...) or function: arg, arg
		if ($m = Regexp::match($cmd, '~^ ([a-z_][a-z0-9_-]*) \s* (?: \( ([^()]*) \) | : (.*) )$~i')) {
			$cmd = $m[1];
			$raw = trim($m[3] ?? $m[2]);
			if ($raw !== '') {
				$args = Regexp::split($raw, '~\s*' . Regexp::quote($this->separator) . '\s*~');
			}
		}

		return new DirectiveNode($cmd, $raw, $args, new Position($offsets[0], strlen($matches[0])));
	}


	public function solve(DirectiveNode $node, Generator $generator): string
	{
		// Directives don't produce output by default, they're processed by passes
		return '';
	}
}
