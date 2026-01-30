<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HtmlCommentNode;
use Texy\Nodes\HtmlTagNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use Texy\Syntax;
use function str_ends_with, strtolower, strtr, substr, trim;


/**
 * Processes HTML tags and comments in input text.
 */
final class HtmlModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerLinePattern(
			$this->parseTag(...),
			'~
				< (/?)                          # tag begin
				([a-z][a-z0-9_:-]{0,50})        # tag name
				(
					(?:
						\s++ [a-z0-9_:-]++ |   # attribute name
						= \s*+ " [^"' . Patterns::MARK . ']*+ " |     # attribute value in double quotes
						= \s*+ \' [^\'' . Patterns::MARK . ']*+ \' |  # attribute value in single quotes
						= [^\s>' . Patterns::MARK . ']++              # attribute value without quotes
					)*
				)
				\s*+
				(/?)                             # self-closing slash
				>
			~is',
			Syntax::HtmlTag,
		);

		$this->texy->registerLinePattern(
			fn(?ParseContext $context, array $matches) => new HtmlCommentNode($matches[1]),
			'~
				<!--
				( [^' . Patterns::MARK . ']*? )
				-->
			~is',
			Syntax::HtmlComment,
		);
	}


	/**
	 * Parses <tag attr="...">
	 * @param  array<?string>  $matches
	 */
	public function parseTag(?ParseContext $context, array $matches): ?HtmlTagNode
	{
		[, $mEnd, $mTag, $mAttr, $mEmpty] = $matches;

		$isStart = $mEnd !== '/';
		$isEmpty = $mEmpty === '/';
		if (!$isEmpty && str_ends_with($mAttr, '/')) {
			$mAttr = substr($mAttr, 0, -1);
			$isEmpty = true;
		}

		// error - can't close empty element
		if ($isEmpty && !$isStart) {
			return null;
		}

		// error - end element with attrs
		$mAttr = trim(strtr($mAttr, "\n", ' '));
		if ($mAttr && !$isStart) {
			return null;
		}

		return new HtmlTagNode(
			$mTag,
			$isStart ? $this->parseAttributes($mAttr) : [],
			closing: !$isStart,
			selfClosing: $isEmpty,
		);
	}


	/** @return array<string, string|bool> */
	private function parseAttributes(string $attrs): array
	{
		$res = [];
		$matches = Regexp::matchAll(
			$attrs,
			<<<'X'
				~
				([a-z0-9_:-]+)                 # attribute name
				\s*
				(?:
					= \s*                      # equals sign
					(
						' [^']* ' |            # single quoted value
						" [^"]* " |            # double quoted value
						[^'"\s]+               # unquoted value
					)
				)?
				~is
				X,
		);

		foreach ($matches as $m) {
			$key = strtolower($m[1]);
			$value = $m[2];
			if ($value == null) {
				$res[$key] = true;
			} elseif ($value[0] === '\'' || $value[0] === '"') {
				$res[$key] = trim(Texy\Helpers::unescapeHtml(substr($value, 1, -1)));
			} else {
				$res[$key] = trim(Texy\Helpers::unescapeHtml($value));
			}
		}

		return $res;
	}
}
