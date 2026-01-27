<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use function implode, krsort, str_contains, trigger_error;


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
		$texy->addHandler('emoticon', $this->solve(...));
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
			'~' . implode('|', $pattern) . '~',
		);
	}


	/**
	 * Parses :-).
	 * @param  array<?string>  $matches
	 */
	public function parse(Texy\InlineParser $parser, array $matches): Texy\HtmlElement|string|null
	{
		/** @var array{string, string} $matches */
		$match = $matches[0];

		// find the closest match
		foreach ($this->icons as $emoticon => $foo) {
			if (str_starts_with($match, $emoticon)) {
				return $this->texy->invokeAroundHandlers('emoticon', $parser, [$emoticon, $match]);
			}
		}

		return null;
	}


	/**
	 * Finish invocation.
	 */
	private function solve(Texy\HandlerInvocation $invocation, string $emoticon): Texy\HtmlElement|string
	{
		$emoji = $this->icons[$emoticon];
		return $this->class
			? (new Texy\HtmlElement('span', ['class' => $this->class]))->setText($emoji)
			: $emoji;
	}
}
