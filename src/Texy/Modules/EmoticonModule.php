<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\InlineParser;
use Texy\Nodes\EmoticonNode;
use Texy\Output\Html\Generator;
use Texy\Position;
use function strlen;


/**
 * Replaces emoticons with images or Unicode characters.
 */
final class EmoticonModule extends Texy\Module
{
	/** @var array<string, string> */
	public array $icons = [
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

	public ?string $class = null;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['emoticon'] = false;
		$texy->htmlGenerator->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		if (empty($this->texy->allowed['emoticon'])) {
			return;
		}

		krsort($this->icons);

		$pattern = [];
		foreach ($this->icons as $key => $foo) {
			$pattern[] = Texy\Regexp::quote($key) . '+'; // last char can be repeated
		}

		$this->texy->registerLinePattern(
			$this->parse(...),
			'~
				(?<= ^ | [\x00-\x20] )
				(' . implode('|', $pattern) . ')
			~',
			'emoticon',
			'~' . implode('|', $pattern) . '~',
		);
	}


	/**
	 * Parses :-).
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parse(InlineParser $parser, array $matches, string $name, array $offsets): ?EmoticonNode
	{
		$match = $matches[0];

		// Find the closest match
		foreach ($this->icons as $emoticon => $_) {
			if (str_starts_with($match, $emoticon)) {
				return new EmoticonNode($emoticon, new Position($offsets[0], strlen($matches[0])));
			}
		}

		return null;
	}


	public function solve(EmoticonNode $node, Generator $generator): string
	{
		$emoji = $this->icons[$node->emoticon];
		return $this->class
			? (new Texy\HtmlElement('span', ['class' => $this->class]))->setText($emoji)->toString($this->texy)
			: $emoji;
	}
}
