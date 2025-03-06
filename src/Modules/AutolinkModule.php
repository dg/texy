<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\{EmailNode, UrlNode};
use Texy\Output\Html;
use Texy\Parsing\ParseContext;
use Texy\{Patterns, Syntax};


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
		$texy->htmlOutput->addRenderer($this->renderUrl(...));
		$texy->htmlOutput->addRenderer($this->renderEmail(...));
	}


	public function beforeParse(): void
	{
		// direct url; characters not allowed in URL <>[\]^`{|}
		$this->texy->addInline(
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
		$this->texy->addInline(
			Syntax::AutolinkEmail,
			'~
				(?<= ^ | [\s([<>] )                 # must be preceded by these chars, > ends a tag
				' . Patterns::Email . '
			~x',
			$this->parseEmail(...),
		);
	}


	/** @param  array{string}  $matches */
	public function parseUrl(ParseContext $context, array $matches): UrlNode
	{
		return new UrlNode(Texy\Helpers::decodeEntities($matches[0]));
	}


	/** @param  array{string}  $matches */
	public function parseEmail(ParseContext $context, array $matches): EmailNode
	{
		return new EmailNode(Texy\Helpers::decodeEntities($matches[0]));
	}


	public function renderUrl(UrlNode $node, Html\Renderer $renderer): Html\Element
	{
		$url = Texy\Helpers::normalizeWww($node->url);

		$el = new Html\Element('a', ['href' => $url]);
		if ($this->texy->linkModule->forceNoFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		$textualUrl = $this->shorten ? Texy\Helpers::shortenUrl($node->url) : $node->url;
		return $el->add($renderer->protect($textualUrl, $renderer::ContentTextual));
	}


	public function renderEmail(EmailNode $node, Html\Renderer $renderer): Html\Element
	{
		$el = new Html\Element('a', ['href' => 'mailto:' . $node->email]);
		$text = $this->texy->obfuscateEmail
			? $renderer->protect(str_replace('@', '&#64;<!-- -->', $node->email), $renderer::ContentTextual)
			: $node->email;
		return $el->add($text);
	}
}
