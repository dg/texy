<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;

use Texy\Position;
use Texy\Regexp;


/**
 * Directive.
 * {{title My article}}
 */
class DirectiveNode extends InlineNode
{
	public function __construct(
		public string $content,
		public ?Position $position = null,
	) {
	}


	/**
	 * Parse directive content into name, value, and arguments.
	 * @return array{name: string, value: ?string, args: list<string>}
	 */
	public function parseContent(string $separator = ','): array
	{
		$cmd = trim($this->content);
		$raw = null;
		$args = [];

		// function (arg, arg, ...) or function: arg, arg
		if ($m = Regexp::match($cmd, '~^ ([a-z_][a-z0-9_-]*) \s* (?: \( ([^()]*) \) | : (.*) )$~ix')) {
			$cmd = $m[1];
			$raw = trim($m[3] ?? $m[2]);
			if ($raw !== '') {
				$args = Regexp::split($raw, '~\s*' . Regexp::quote($separator) . '\s*~');
			}
		}

		return ['name' => $cmd, 'value' => $raw, 'args' => $args];
	}
}
