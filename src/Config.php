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
	 * locale => quotes, opening and closing double, then single ones
	 * @var array<string, array{quotes: list<string>}>
	 */
	public array $locales = [
		'cs' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"]],
		'de' => ['quotes' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"]],
		'en' => ['quotes' => ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"]],
		'fr' => ['quotes' => ["\u{00AB}", "\u{00BB}", "\u{2039}", "\u{203A}"]],
		'pl' => ['quotes' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"]],
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
	 * @var array<string, Nodes\ImageDefinitionNode>
	 * @internal
	 */
	public private(set) array $imageDefinitions = [];

	/**
	 * @var array<string, Nodes\LinkDefinitionNode>
	 * @internal
	 */
	public private(set) array $linkDefinitions = [];


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


	/**
	 * Adds an image definition that lasts across documents; a document can override it by its own.
	 */
	public function addImageDefinition(
		string $name,
		string $url,
		?int $width = null,
		?int $height = null,
		?string $alt = null,
	): void
	{
		$this->imageDefinitions[Helpers::toLower($name)] = new Nodes\ImageDefinitionNode($name, $url, $alt, $width, $height);
	}


	/**
	 * Adds a link definition that lasts across documents; a document can override it by its own.
	 */
	public function addLinkDefinition(string $name, string $url): void
	{
		$this->linkDefinitions[Helpers::toLower($name)] = new Nodes\LinkDefinitionNode($name, $url);
	}
}
