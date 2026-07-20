<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\EmailNode;
use Texy\Nodes\UrlNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Range;
use Texy\Syntax;
use function strlen;


/**
 * Autodetects URLs and email addresses in text.
 */
final class AutolinkModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		// direct url; characters not allowed in URL <>[\]^`{|}
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches, array $offsets) => new UrlNode($matches[0], new Range($offsets[0], strlen($matches[0]))),
			'~
				(?<= ^ | [\s([<:] )                # must be preceded by these chars
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~x',
			Syntax::AutolinkUrl,
		);

		// direct email
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches, array $offsets) => new EmailNode($matches[0], new Range($offsets[0], strlen($matches[0]))),
			'~
				(?<= ^ | [\s([<] )                  # must be preceded by these chars
				' . Patterns::Email . '
			~x',
			Syntax::AutolinkEmail,
		);
	}
}
