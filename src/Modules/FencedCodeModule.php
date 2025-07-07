<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\CodeBlockNode;
use Texy\Parsing\{OffsetMap, ParseContext};
use Texy\{Range, Syntax};
use function strlen;


/**
 * Processes fenced code blocks (```language).
 */
final class FencedCodeModule extends Texy\Module
{
	public function register(Texy\Texy $texy): void
	{
		$texy->addBlock(
			Syntax::FencedCode,
			'~^
				(`{3,}) \ *+                        # opening fence (1)
				([^\n`]*)                           # info string (2)
				$
				((?: \n (?! \1 `*+ \ *+ $ ) .*+ )*) # content (3): lines that are not a valid closing fence
				(?:
					\n \1 `*+ \ *+ $ |              # closing fence (same length or longer)
					\z                              # or end of input
				)
			~mx',
			$this->parse(...),
		);
	}


	/**
	 * Parses ```language fenced code blocks; content is verbatim.
	 * @param  array{string, string, string, string}  $matches
	 * @param  array{int, int, int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): CodeBlockNode
	{
		[, , $mInfo, $mContent] = $matches;

		// a Texy modifier may trail the info string: ```php .{file: Foo.php}
		$mod = null;
		if ($m = Texy\Regexp::match($mInfo, '~' . Texy\Patterns::MODIFIER_H . '$~x', captureOffset: true)) {
			$mod = Texy\Modifier::parse($m[1][0], $offsets[2] + $m[1][1]);
			$mInfo = substr($mInfo, 0, $m[0][1]);
		}

		// first word of the info string is the language (GFM)
		$language = Texy\Regexp::split(trim($mInfo), '~\s+~', limit: 2)[0];

		$code = substr($mContent, 1); // drop the separator "\n"; intentional blank lines stay
		return new CodeBlockNode(
			$code,
			$language === '' ? null : $language,
			$mod,
			new Range($offsets[0], strlen($matches[0])),
			OffsetMap::fromLines(OffsetMap::linesOf($code, $offsets[3] + 1)),
		);
	}
}
