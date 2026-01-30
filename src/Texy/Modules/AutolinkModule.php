<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\EmailNode;
use Texy\Nodes\UrlNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Syntax;


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
			fn(ParseContext $context, array $matches) => new UrlNode($matches[0]),
			'~
				(?<= ^ | [\s([<:] )                # must be preceded by these chars
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~',
			Syntax::AutolinkUrl,
		);

		// direct email
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches) => new EmailNode($matches[0]),
			'~
				(?<= ^ | [\s([<] )                  # must be preceded by these chars
				[' . Patterns::CHAR . ']                 # first char
				[0-9.+_' . Patterns::CHAR . '-]{0,63}    # local part
				@
				[0-9.+_' . Patterns::CHAR . '\x{ad}-]{1,252} # domain
				\.
				[' . Patterns::CHAR . '\x{ad}]{2,19}     # TLD
			~',
			Syntax::AutolinkEmail,
		);
	}
}
