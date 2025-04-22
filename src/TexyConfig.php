<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function in_array;


/**
 * The configuration of a Texy document, adding what only the Texy language knows.
 */
final class TexyConfig extends Config
{
	/** how the levels of headings are computed */
	public HeadingBalancing $headingBalancing = HeadingBalancing::Dynamic;

	/** @var array<string, int>  underline character => level 1..6 */
	public array $headingLevels = [
		'#' => 1,
		'*' => 2,
		'=' => 3,
		'-' => 4,
	];

	/** surrounded headings: more #### means a higher heading? */
	public bool $headingMoreMeansHigher = true;

	/** @var array<string, array{string, Nodes\ListType, 2?: string}> [regex, type, next-regex?] */
	public array $listBullets = [
		'*' => ['\* [ \t]', Nodes\ListType::Unordered],
		'-' => ['[\x{2013}-] (?! [>-] )', Nodes\ListType::Unordered],
		'+' => ['\+ [ \t]', Nodes\ListType::Unordered],
		'1.' => ['1 \. [ \t]' /* not \d! */, Nodes\ListType::Decimal, '\d{1,3} \. [ \t]'],
		'1)' => ['\d{1,3} \) [ \t]', Nodes\ListType::Decimal],
		'I.' => ['I \. [ \t]', Nodes\ListType::UpperRoman, '[IVX]{1,4} \. [ \t]'],
		'I)' => ['[IVX]+ \) [ \t]', Nodes\ListType::UpperRoman], // before A) !
		'a)' => ['[a-z] \) [ \t]', Nodes\ListType::LowerAlpha],
		'A)' => ['[A-Z] \) [ \t]', Nodes\ListType::UpperAlpha],
	];

	/** join consecutive lines into one paragraph? false makes every newline a line break */
	public bool $mergeLines = true;

	/** can a phrase carry a link? */
	public bool $phraseLinks = true;

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
	 * @throws InvalidArgumentException
	 * @internal
	 */
	public function validate(): void
	{
		parent::validate();
		foreach ($this->headingLevels as $char => $level) {
			if (!in_array($char, ['#', '*', '=', '-'], true)) {
				throw new InvalidArgumentException("TexyConfig::\$headingLevels has a key '$char', only # * = - underline a heading.");
			}

			if ($level < 1 || $level > 6) {
				throw new InvalidArgumentException("TexyConfig::\$headingLevels['$char'] must be 1..6, $level given.");
			}
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
