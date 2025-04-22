<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\EmoticonNode;
use Texy\Parsing\ParseContext;
use Texy\Syntax;


/**
 * Replaces emoticons with images or Unicode characters.
 */
final class EmoticonModule extends Texy\Module
{
	private bool $warnedIcons = false;


	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$registry->declare(Syntax::Emoticon, enabled: false);

		if (!$this->warnedIcons && str_contains(implode('', $this->config->emoticons), '.')) {
			$this->warnedIcons = true;
			trigger_error('EmoticonModule: using image files is deprecated, use Unicode characters instead.', E_USER_DEPRECATED);
		}

		$icons = $this->config->emoticons;
		krsort($icons);
		$pattern = [];
		foreach ($icons as $key => $foo) {
			$pattern[] = Texy\Regexp::quote($key) . '+'; // last char can be repeated
		}

		$registry->addInline(
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
		foreach ($this->config->emoticons as $emoticon => $_) {
			if (str_starts_with($match, $emoticon)) {
				$node = new EmoticonNode($emoticon);
				$node->resolved = $this->config->emoticons[$emoticon];
				return $node;
			}
		}

		return null;
	}
}
