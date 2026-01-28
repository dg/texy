<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\EmoticonNode;
use Texy\Output\Html;
use Texy\ParseContext;


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

	/** CSS class for emoticons */
	public ?string $class = null;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['emoticon'] = false;
		$texy->htmlGenerator->registerHandler($this->solve(...));
	}


	public function beforeParse(string &$text): void
	{
		if (str_contains(implode('', $this->icons), '.')) {
			trigger_error('EmoticonModule: using image files is deprecated, use Unicode characters instead.', E_USER_DEPRECATED);
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
		);
	}


	/**
	 * Parses :-).
	 * @param  array<?string>  $matches
	 */
	public function parse(ParseContext $context, array $matches): ?EmoticonNode
	{
		$match = $matches[0];

		// Find the closest match
		foreach ($this->icons as $emoticon => $_) {
			if (str_starts_with($match, $emoticon)) {
				return new EmoticonNode($emoticon);
			}
		}

		return null;
	}


	public function solve(EmoticonNode $node, Html\Generator $generator): Html\Element|string
	{
		$emoji = $this->icons[$node->emoticon];
		return $this->class
			? (new Html\Element('span', ['class' => $this->class]))->setText($emoji)
			: $emoji;
	}
}
