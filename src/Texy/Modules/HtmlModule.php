<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\HtmlCommentNode;
use Texy\Nodes\HtmlTagNode;
use Texy\ParseContext;
use Texy\Range;
use Texy\Regexp;
use Texy\Syntax;
use function str_ends_with, strlen, strtolower, strtr, substr, trim;


/**
 * Processes HTML tags and comments in input text.
 */
final class HtmlModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->addHandler('afterParse', $this->processPassthrough(...));
	}


	/**
	 * Pairs passthrough tags into HtmlElementNode trees and evaluates the
	 * tag whitelist over them (transform phase): rejected tags become text.
	 */
	public function processPassthrough(Texy\Nodes\DocumentNode $doc): void
	{
		if (empty($this->texy->allowed[Syntax::HtmlTag])) {
			return;
		}

		(new Texy\Passes\HtmlPairingPass)->process($doc);
		(new Texy\Passes\HtmlSanitizePass($this->texy->htmlPolicy))
			->process($doc);
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
						\s++ [a-z0-9_:-]++ |          # attribute name
						= \s*+ " [^"]*+ " |           # attribute value in double quotes
						= \s*+ \' [^\']*+ \' |        # attribute value in single quotes
						= [^\s>]++                    # attribute value without quotes
					)*
				)
				\s*+
				(/?)                             # self-closing slash
				>
			~isx',
			Syntax::HtmlTag,
		);

		$this->texy->registerLinePattern(
			$this->parseComment(...),
			'~
				<!--
				( .*? )
				-->
			~isx',
			Syntax::HtmlComment,
		);
	}


	/**
	 * Parses <!-- comment -->
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parseComment(ParseContext $context, array $matches, array $offsets): HtmlCommentNode
	{
		return new HtmlCommentNode($matches[1], new Range($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses <tag attr="...">
	 * @param  array{string, string, string, string, string}  $matches
	 * @param  array{int, int, int, int, int}  $offsets
	 */
	public function parseTag(ParseContext $context, array $matches, array $offsets): ?HtmlTagNode
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
			range: new Range($offsets[0], strlen($matches[0])),
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
				~isx
				X,
		);

		/** @var array{string, string, ?string} $m */
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
