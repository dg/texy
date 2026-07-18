<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * URL scheme security policy: which schemes may appear in links and in images.
 * A null pattern means no restriction; scheme-less (relative) URLs always pass.
 * Held by Texy::$urlPolicy and enforced by every output format.
 */
final class UrlPolicy
{
	/** regex matching allowed link schemes, e.g. '~https?:|ftp:|mailto:~A'; null = allow all */
	public ?string $linkPattern = null;

	/** regex matching allowed image schemes, e.g. '~https?:~A'; null = allow all */
	public ?string $imagePattern = null;


	public function isLinkAllowed(string $url): bool
	{
		return $this->isAllowed($url, $this->linkPattern);
	}


	public function isImageAllowed(string $url): bool
	{
		return $this->isAllowed($url, $this->imagePattern);
	}


	private function isAllowed(string $url, ?string $pattern): bool
	{
		return $pattern === null
			|| !Regexp::match($url, '~\s*[a-z][a-z0-9+.-]{0,20}:~Ai') // no scheme (relative URL)
			|| (bool) Regexp::match($url, $pattern);
	}
}
