<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Helpers;
use Texy\Nodes\EmailNode;
use Texy\Nodes\UrlNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use function str_replace;


/**
 * Autodetects URLs and email addresses in text.
 */
final class AutolinkModule extends Texy\Module
{
	/** shorten URLs to more readable form? */
	public bool $shorten = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlOutput->registerHandler($this->solveUrl(...));
		$texy->htmlOutput->registerHandler($this->solveEmail(...));
	}


	public function beforeParse(string &$text): void
	{
		// direct url; characters not allowed in URL <>[\]^`{|}
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches) => new UrlNode((string) $matches[0]),
			'~
				(?<= ^ | [\s([<:] )                # must be preceded by these chars
				(?: https?:// | www\. | ftp:// )   # protocol or www
				[0-9.' . Patterns::CHAR . '-]      # first char
				[/\d' . Patterns::CHAR . '+.\~%&?@=_:;#$!,*()\x{ad}-]{1,1000}  # URL body
				[/\d' . Patterns::CHAR . '+\~?@=_#$*]  # last char
			~x',
			'link/url',
		);

		// direct email
		$this->texy->registerLinePattern(
			fn(ParseContext $context, array $matches) => new EmailNode((string) $matches[0]),
			'~
				(?<= ^ | [\s([<] )                  # must be preceded by these chars
				' . Patterns::Email . '
			~x',
			'link/email',
		);
	}


	/**
	 * Generates HTML for UrlNode.
	 */
	public function solveUrl(UrlNode $node, Html\Renderer $generator): Html\Element
	{
		$url = strncasecmp($node->url, 'www.', 4) === 0
			? 'http://' . $node->url
			: $node->url;

		$text = $this->shorten && preg_match('~^(https?://|ftp://|www\.|/)~i', $node->url)
			? Helpers::shortenUrl($node->url)
			: $node->url;

		$el = new Html\Element('a', ['href' => $url]);
		if ($this->texy->linkModule->forceNoFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		return $el->add($this->texy->protect($text, $this->texy::CONTENT_TEXTUAL));
	}


	/**
	 * Generates HTML for EmailNode.
	 */
	public function solveEmail(EmailNode $node, Html\Renderer $generator): Html\Element
	{
		$el = new Html\Element('a', ['href' => 'mailto:' . $node->email]);
		$email = $this->texy->obfuscateEmail
			? $this->texy->protect(str_replace('@', '&#64;<!-- -->', $node->email), $this->texy::CONTENT_TEXTUAL)
			: $node->email;
		return $el->add($email);
	}
}
