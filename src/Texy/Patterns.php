<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Regular expression patterns.
 */
class Patterns
{
	// Unicode character classes
	public const CHAR = 'A-Za-z\x{C0}-\x{2FF}\x{370}-\x{1EFF}';

	// internal marker bytes: the TextRun image alphabet (\x15 textual, \x16 replaced,
	// \x17 markup) and the deprecated protection marks; never present in parsed input
	public const MARK = '\x14-\x1F';

	// modifier .(title)[class]{style}
	public const MODIFIER = <<<'X'
		(?x:
			\ *+ (?<= \ | ^ )
			\.
			((?:
				\( (?: \\\) | [^)\n] )++ \) | # title
				\[ [^]\n]++ ] |               # class
				\{ [^}\n]++ }                 # style
			){1,3}?)
		)
		X;

	// modifier .(title)[class]{style}<>
	public const MODIFIER_H = <<<'X'
		(?x:
			\ *+ (?<= \ | ^ )
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
	public const MODIFIER_HV = <<<'X'
		(?x:
			\ *+ (?<= \ | ^ )
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

	// links, url - doesn't end by :).,!?
	public const LINK_URL = <<<'X'
		(?x:
			\[ [^]\n]++ ]                    # link text in brackets
			|
			(?= [\w/+.\~%&?@=_#$] )          # URL must start with these chars
			\S{0,1000}?                      # URL body
			[^:);,.!?\s]                     # URL must not end with these chars
		)
		X;

	public const Email = '(?x:
		[' . self::CHAR . ']                 # first char
		[0-9.+_' . self::CHAR . '-]{0,63}    # local part
		@
		[0-9.+_' . self::CHAR . '\x{ad}-]{1,252} # domain
		\.
		[' . self::CHAR . '\x{ad}]{2,19}     # TLD
	)';
}
