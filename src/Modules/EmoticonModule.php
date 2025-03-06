<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\EmoticonNode;
use Texy\Output\Html;
use Texy\Parsing\ParseContext;
use Texy\Syntax;


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

	private bool $warnedIcons = false;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlOutput->addRenderer($this->render(...));
	}


	public function beforeParse(): void
	{
		$this->texy->declare(Syntax::Emoticon, enabled: false);

		if (!$this->warnedIcons && str_contains(implode('', $this->icons), '.')) {
			$this->warnedIcons = true;
			trigger_error('EmoticonModule: using image files is deprecated, use Unicode characters instead.', E_USER_DEPRECATED);
		}

		$icons = $this->icons;
		krsort($icons);
		$pattern = [];
		foreach ($icons as $key => $foo) {
			$pattern[] = Texy\Regexp::quote($key) . '+'; // last char can be repeated
		}

		$this->texy->addInline(
			Syntax::Emoticon,
			'~
				(?<= ^ | [\x00-\x20] )
				(' . implode('|', $pattern) . ')
			~x',
			$this->parse(...),
		);
	}


	/**
	 * Parses :-).
	 * @param  array{string, string}  $matches
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


	public function render(EmoticonNode $node, Html\Renderer $renderer): Html\Element|string
	{
		$emoji = $this->icons[$node->emoticon] ?? $node->emoticon;

		return $this->class
			? new Html\Element('span', ['class' => $this->class])->setText($emoji)
			: $emoji;
	}
}
