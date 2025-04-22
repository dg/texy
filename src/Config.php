<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function count;


/**
 * What a document means in any language: typography, hyphenation and headings.
 * Read by the passes when they run, so a change takes effect on the next one.
 */
class Config
{
	/** apply typographic corrections (quotes, dashes, ellipsis, non-breaking spaces)? */
	public bool $typography = true;

	/** key of $locales */
	public string $locale = 'cs';

	/** @var array<string, list<string>>  locale => four quotes: opening and closing double, then single ones */
	public array $locales = [
		'cs' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"],
		'de' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"],
		'en' => ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"],
		'fr' => ["\u{00AB}", "\u{00BB}", "\u{2039}", "\u{203A}"],
		'pl' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"],
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

		if (count($this->locales[$this->locale]) !== 4) {
			throw new InvalidArgumentException("Config::\$locales['$this->locale'] must be a list of four quotes.");
		}
	}
}
