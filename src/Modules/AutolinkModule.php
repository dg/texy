<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\{EmailNode, UrlNode};
use Texy\Parsing\ParseContext;
use Texy\{Patterns, Range, Syntax};
use function strlen;


/**
 * Autodetects URLs and email addresses in text.
 */
final class AutolinkModule extends Texy\Module
{
	public function register(Texy\Parsing\Registry $registry): void
	{
		// direct url; characters not allowed in URL <>[\]^`{|}
		$registry->addInline(
			Syntax::AutolinkUrl,
			'~
				(?<= ^ | [\s([<:>] )               # must be preceded by these chars, > ends a tag
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~x',
			$this->parseUrl(...),
		);

		// direct email
		$registry->addInline(
			Syntax::AutolinkEmail,
			'~
				(?<= ^ | [\s([<>] )                 # must be preceded by these chars, > ends a tag
				' . Patterns::Email . '
			~x',
			$this->parseEmail(...),
		);
	}


	/**
	 * @param  array{string}  $matches
	 * @param  array{int}  $offsets
	 */
	public function parseUrl(ParseContext $context, array $matches, array $offsets): UrlNode
	{
		return new UrlNode(Texy\Helpers::decodeEntities($matches[0]), new Range($offsets[0], strlen($matches[0])));
	}


	/**
	 * @param  array{string}  $matches
	 * @param  array{int}  $offsets
	 */
	public function parseEmail(ParseContext $context, array $matches, array $offsets): EmailNode
	{
		return new EmailNode(Texy\Helpers::decodeEntities($matches[0]), new Range($offsets[0], strlen($matches[0])));
	}
}
