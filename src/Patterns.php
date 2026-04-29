<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Regular expression patterns.
 */
class Patterns
{
	// Unicode character classes
	public const Letter = 'A-Za-z\x{C0}-\x{2FF}\x{370}-\x{1EFF}';

	// modifier .(title)[class]{style}
	public const Modifier = <<<'X'
		(?x:
			[ \t]*+ (?<= [ \t] | ^ )
			\.
			((?:
				\( (?: \\\) | [^)\n] )++ \) | # title
				\[ [^]\n]++ ] |               # class
				\{ [^}\n]++ }                 # style
			){1,3}?)
		)
		X;

	// modifier .(title)[class]{style}<>
	public const ModifierHAlign = <<<'X'
		(?x:
			[ \t]*+ (?<= [ \t] | ^ )
			\.
			((?:
				\( (?: \\\) | [^)\n] )++ \) | # title
				\[ [^]\n]++ ] |               # class
				\{ [^}\n]++ } |               # style
				<> | > | = | <                # horizontal alignment
			){1,4}?)
		)
		X;

	// modifier .(title)[class]{style}<>^
	public const ModifierHVAlign = <<<'X'
		(?x:
			[ \t]*+ (?<= [ \t] | ^ )
			\.
			((?:
				\( (?: \\\) | [^)\n] )++ \) | # title
				\[ [^]\n]++ ] |               # class
				\{ [^}\n]++ } |               # style
				<> | > | = | < |              # horizontal alignment
				\^ | - | _                    # vertical alignment
			){1,5}?)
		)
		X;

	// links, url - doesn't end by :;,.!? and takes ) only to close its own (, used with the U flag
	public const LinkUrl = <<<'X'
		(?x:
			\[ [^]\n]++ ]                    # link text in brackets
			|
			(?= [\w/+.\~%&?@=_#$] )          # URL must start with these chars
			(?: [^\s()] | \( [^\s()]{0,100}+ \) | \( )+?  # URL body, parentheses balanced
			(?<! [:;,.!?] )                  # URL must not end with these chars
		)
		X;

	public const Email = '(?x:
		[' . self::Letter . ']                 # first char
		[0-9.+_' . self::Letter . '-]{0,63}    # local part
		@
		[0-9.+_' . self::Letter . '-]{1,252}   # domain
		\.
		[' . self::Letter . ']{2,19}           # TLD
	)';
}
