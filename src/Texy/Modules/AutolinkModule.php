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
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Syntax;
use function iconv_strlen, iconv_substr, str_contains, str_replace, strlen, strncasecmp;


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
		$texy->htmlGenerator->registerHandler($this->solveUrl(...));
		$texy->htmlGenerator->registerHandler($this->solveEmail(...));
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


	/**
	 * Generates HTML for UrlNode.
	 */
	public function solveUrl(UrlNode $node, Html\Generator $generator): Html\Element
	{
		$url = strncasecmp($node->url, 'www.', 4) === 0
			? 'http://' . $node->url
			: $node->url;

		$el = new Html\Element('a', ['href' => $url]);
		if ($this->texy->linkModule->forceNoFollow && str_contains($url, '//')) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Protect URL text from typography/longwords processing
		return $el->add($this->texy->protect($this->textualUrl($node->url), $this->texy::CONTENT_TEXTUAL));
	}


	/**
	 * Generates HTML for EmailNode.
	 */
	public function solveEmail(EmailNode $node, Html\Generator $generator): Html\Element
	{
		$el = new Html\Element('a', ['href' => 'mailto:' . $node->email]);
		$text = $this->texy->obfuscateEmail
			? $this->texy->protect(str_replace('@', '&#64;<!-- -->', $node->email), $this->texy::CONTENT_TEXTUAL)
			: $node->email;
		return $el->add($text);
	}


	/**
	 * Returns textual representation of URL (shortened for display).
	 */
	public function textualUrl(string $url): string
	{
		// Only shorten URLs that start with http://, https://, ftp://, www., or /
		if (!$this->shorten || !preg_match('~^(https?://|ftp://|www\.|/)~i', $url)) {
			return $url;
		}

		$raw = strncasecmp($url, 'www.', 4) === 0
			? 'none://' . $url
			: $url;

		// parse_url() in PHP damages UTF-8 - use regular expression
		if (!preg_match('~^
			(?: (?P<scheme> [a-z]+ ) : )?
			(?: // (?P<host> [^/?#]+ ) )?
			(?P<path> (?: / | ^ ) (?! / ) [^?#]* )?
			(?: \? (?P<query> [^#]* ) )?
			(?: \# (?P<fragment> .* ) )?
			$
		~x', $raw, $parts)) {
			return $url;
		}

		$res = '';
		// Add scheme if not 'none' (www. URLs are mapped to none://)
		if (($parts['scheme'] ?? null) !== null && $parts['scheme'] !== 'none') {
			$res .= $parts['scheme'] . '://';
		}

		if (($parts['host'] ?? null) !== null) {
			$res .= $parts['host'];
		}

		if (($parts['path'] ?? null) !== null) {
			$res .= (iconv_strlen($parts['path'], 'UTF-8') > 16 ? ("/\u{2026}" . iconv_substr($parts['path'], -12, 12, 'UTF-8')) : $parts['path']);
		}

		if (($parts['query'] ?? '') > '') {
			$res .= iconv_strlen($parts['query'], 'UTF-8') > 4
				? "?\u{2026}"
				: ('?' . $parts['query']);
		} elseif (($parts['fragment'] ?? '') > '') {
			$res .= iconv_strlen($parts['fragment'], 'UTF-8') > 4
				? "#\u{2026}"
				: ('#' . $parts['fragment']);
		}

		return $res;
	}
}
