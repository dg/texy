<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_key_exists, count;


/**
 * The configuration of a document. Read when parsing and by the passes when they run,
 * so a change takes effect on the next one.
 */
final class Config
{
	/** apply typographic corrections (quotes, dashes, ellipsis, non-breaking spaces)? */
	public bool $typography = true;

	/** key of $locales */
	public string $locale = 'cs';

	/**
	 * locale => quotes (opening and closing double, then single ones) and optionally
	 * one-letter words that may not end a line, bound to the next word
	 * @var array<string, array{quotes: list<string>, oneLetterWords?: string}>
	 */
	public array $locales = [
		'bg' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"]],
		'cs' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"], 'oneLetterWords' => 'ksvzouiKSVZOUIA'],
		'de' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"]],
		// languages using guillemets nest the inner level in the quotes given as single
		'el' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"]],
		'en' => ['quotes' => ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"]],
		'es' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"]],
		'fr' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{2039}", "\u{203A}"]],
		'hu' => ['quotes' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"]],
		'it' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"]],
		// Japanese corner brackets: 「…」 primary, 『…』 nested
		'ja' => ['quotes' => ["\u{300C}", "\u{300D}", "\u{300E}", "\u{300F}"]],
		'pl' => ['quotes' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"], 'oneLetterWords' => 'wzioauWZIOAU'],
		// Brazilian usage, which is what the pt documentation follows
		'pt' => ['quotes' => ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"]],
		'ro' => ['quotes' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"]],
		'ru' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{201E}", "\u{201C}"]],
		'sk' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"], 'oneLetterWords' => 'ksvzouaiKSVZOUAI'],
		'sl' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"]],
		'tr' => ['quotes' => ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"]],
		'uk' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{201E}", "\u{201C}"]],
	];

	/** break long words with soft hyphens? */
	public bool $hyphenation = true;

	/** length of a word that gets hyphenated */
	public int $hyphenationWordLimit = 20;

	/** level of the top heading, 1..6 */
	public int $headingTop = 1;

	/** generate an ID for every heading? */
	public bool $headingIds = false;

	/** prefix of generated IDs */
	public string $headingIdPrefix = 'toc-';

	/** how the levels of headings are computed */
	public HeadingBalancing $headingBalancing = HeadingBalancing::Dynamic;

	/** surrounded headings: more #### means a higher heading? */
	public bool $headingMoreMeansHigher = true;

	/** join consecutive lines into one paragraph? false makes every newline a line break */
	public bool $mergeLines = true;

	/** @var array<string, string> emoticon → emoji/image mapping */
	public array $emoticons = [
		':-)' => '🙂',
		':-(' => '☹',
		';-)' => '😉',
		':-D' => '😁',
		'8-O' => '😮',
		'8-)' => '😄',
		':-?' => '😕',
		':-x' => '😶',
		':-P' => '😛',
		':-|' => '😐',
	];

	/**
	 * Resolver of links whose target is a name in brackets the document does not define,
	 * "text":[name] or [name]; it is called once per link with the name as written, #fragment included.
	 * It returns the URL, where %s stands for the text of the link, or null to keep the name as the address.
	 * It may change the link, e.g. its content or modifier.
	 * @var ?\Closure(string, Nodes\LinkNode): ?string
	 */
	public ?\Closure $linkResolver = null;

	/**
	 * Handler of every directive {{name ...}} except {{texy: ...}}. The directive is replaced
	 * by the node the handler returns, so returning the directive keeps it; anything else, nothing
	 * included, removes it. Without a handler directives stay and render as the author wrote them.
	 * @var ?\Closure(Nodes\DirectiveNode): mixed
	 */
	public ?\Closure $directiveHandler = null;

	/**
	 * Handler of delimited blocks /--type: gets the type in lower case, the content without its
	 * indentation, the parameter, the modifier and $next(), which returns what Texy makes of the block.
	 * Returns the node of the block, or null to leave it as text.
	 * @var ?\Closure(Parsing\ParseContext, string, string, ?string, ?Modifier, \Closure(): ?Nodes\BlockNode): ?Nodes\BlockNode
	 */
	public ?\Closure $delimitedBlockHandler = null;


	/** @internal */
	public function resolveLink(string $name, Nodes\LinkNode $link): ?string
	{
		$url = $this->linkResolver?->__invoke($name, $link);
		return $url === null ? null : Helpers::insertLinkText($url, $link);
	}


	/**
	 * Fails on values that would otherwise surface as a random warning in the middle of processing.
	 * @throws InvalidArgumentException
	 * @internal
	 */
	public function validate(): void
	{
		if ($this->headingTop < 1 || $this->headingTop > 6) {
			throw new InvalidArgumentException("Config::\$headingTop must be 1..6, $this->headingTop given.");
		}

		if (!isset($this->locales[$this->locale])) {
			throw new InvalidArgumentException("Config::\$locale '$this->locale' is not a key of Config::\$locales.");
		}

		$row = $this->locales[$this->locale];
		if (!array_key_exists('quotes', $row) || count($row['quotes']) !== 4) {
			throw new InvalidArgumentException("Config::\$locales['$this->locale']['quotes'] must be a list of four quotes.");
		}
	}
}
