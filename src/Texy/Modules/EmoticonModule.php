<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Compat, Range, Syntax};
use Texy\Nodes\EmoticonNode;
use Texy\Parsing\ParseContext;
use function strlen;


/**
 * Replaces emoticons with images or Unicode characters.
 */
final class EmoticonModule extends Texy\Module
{
	/** @var array<string, string> emoticon → emoji/image mapping */
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

	private bool $warnedIcons = false;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::Emoticon] = false;
		$texy->addHandler($this->resolveEmoticons(...));
	}


	/**
	 * Writes resolved emoji into EmoticonNode so the AST is semantically complete
	 * and generators need not reach into this module.
	 */
	public function resolveEmoticons(Texy\Nodes\DocumentNode $doc): void
	{
		if (empty($this->texy->allowed[Syntax::Emoticon])) { // disabled → no EmoticonNodes in AST
			return;
		}

		foreach (Texy\NodeHelpers::find($doc, EmoticonNode::class) as $node) {
			$node->resolved = $this->icons[$node->emoticon] ?? $node->emoticon;
		}
	}


	public function beforeParse(): void
	{
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

		$this->texy->registerLinePattern(
			$this->parse(...),
			'~
				(?<= ^ | [\x00-\x20] )
				(' . implode('|', $pattern) . ')
			~x',
			Syntax::Emoticon,
		);
	}


	/**
	 * Parses :-).
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?EmoticonNode
	{
		$match = $matches[0];

		// Find the closest match
		foreach ($this->icons as $emoticon => $_) {
			if (str_starts_with($match, $emoticon)) {
				return new EmoticonNode($emoticon, new Range($offsets[0], strlen($matches[0])));
			}
		}

		return null;
	}


	/**
	 * @deprecated use $texy->htmlOutput->emoticonClass etc. instead
	 */
	public function &__get(string $name): mixed
	{
		return Compat\Legacy::ref($this->texy, Compat\Legacy::OfModule['emoticonModule'], '$texy->emoticonModule', $name, 'read');
	}


	/**
	 * @deprecated use $texy->htmlOutput->emoticonClass etc. instead
	 */
	public function __set(string $name, mixed $value): void
	{
		Compat\Legacy::set($this->texy, Compat\Legacy::OfModule['emoticonModule'], '$texy->emoticonModule', $name, $value);
	}


	public function __isset(string $name): bool
	{
		return Compat\Legacy::isSet($this->texy, Compat\Legacy::OfModule['emoticonModule'], $name);
	}
}
