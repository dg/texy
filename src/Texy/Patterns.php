<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Regular expression patterns
 */
class Patterns
{
	// Unicode character classes
	public const CHAR = 'A-Za-z\x{C0}-\x{2FF}\x{370}-\x{1EFF}';

	// marking meta-characters
	// any mark:              \x14-\x1F
	// CONTENT_MARKUP mark:   \x17-\x1F
	// CONTENT_REPLACED mark: \x16-\x1F
	// CONTENT_TEXTUAL mark:  \x15-\x1F
	// CONTENT_BLOCK mark:    \x14-\x1F
	public const MARK = '\x14-\x1F';

	// modifier .(title)[class]{style}
	public const MODIFIER = <<<'X'
		(?:
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
		(?:
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
		(?:
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

	// images   [* urls .(title)[class]{style} >]   '\[\* *+([^\n'.MARK.']{1,1000})'.MODIFIER.'? *+(\*|(?<!<)>|<)\]'
	public const IMAGE = <<<'X'
		\[\* \ *+
		( [^\n\x14-\x1F]{1,1000} )       # URL
		(?:
			\ *+ (?<= \ | ^ )
			\.
			((?:
				\( [^)\n]++ \) |         # title
				\[ [^]\n]++ ] |          # class
				\{ [^}\n]++ }            # style
			){1,3}?)
		)?
		\ *+
		( \* | (?<!<) > | < )            # alignment
		]
		X;

	// links, url - doesn't end by :).,!?
	public const LINK_URL = <<<'X'
		(?:
			\[ [^]\n]++ ]                    # link text in brackets
			|
			(?= [\w/+.\~%&?@=_#$] )          # URL must start with these chars
			[^\s\x14-\x1F]{0,1000}?          # URL body
			[^:);,.!?\s\x14-\x1F]            # URL must not end with these chars
		)
		X;
}
