<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Regexp;


/**
 * Directive.
 * {{title My article}}
 */
class DirectiveNode extends InlineNode
{
	/** the name, or the whole text when it has no value */
	public string $name;

	/** what follows the name after a colon or in parentheses */
	public ?string $value = null;

	/** @var list<string>  the value split by commas */
	public array $args = [];


	public function __construct(
		/** the source between the braces */
		public string $text,
	) {
		$this->name = trim($text);
		// name (arg, arg, ...) or name: arg, arg
		if ($m = Regexp::match($this->name, '~^ ([a-z_][a-z0-9_-]*) \s* (?: \( ([^()]*) \) | : (.*) )$~ix')) {
			/** @var array{string, string, ?string, ?string} $m */
			$this->name = $m[1];
			$this->value = trim((string) ($m[3] ?? $m[2]));
			$this->args = $this->value === '' ? [] : Regexp::split($this->value, '~\s*,\s*~');
		}
	}
}
