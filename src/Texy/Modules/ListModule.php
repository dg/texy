<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\BlockParser;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use Texy\Regexp;
use function count, implode, ord, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	/** @var array<string, array{string, int, string, 3?: string}> [regex, ordered?, list-style-type, next-regex?] */
	public array $bullets = [
		// first-rexexp ordered? list-style-type next-regexp
		'*' => ['\* [ \t]', 0, ''],
		'-' => ['[\x{2013}-] (?! [>-] )', 0, ''],
		'+' => ['\+ [ \t]', 0, ''],
		'1.' => ['1 \. [ \t]', /* not \d !*/ 1, '', '\d{1,3} \. [ \t]'],
		'1)' => ['\d{1,3} \) [ \t]', 1, ''],
		'I.' => ['I \. [ \t]', 1, 'upper-roman', '[IVX]{1,4} \. [ \t]'],
		'I)' => ['[IVX]+ \) [ \t]', 1, 'upper-roman'], // before A) !
		'a)' => ['[a-z] \) [ \t]', 1, 'lower-alpha'],
		'A)' => ['[A-Z] \) [ \t]', 1, 'upper-alpha'],
	];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['list'] = true;
		$texy->allowed['list/definition'] = true;
		$texy->htmlGenerator->registerHandler($this->solveList(...));
		$texy->htmlGenerator->registerHandler($this->solveItem(...));
		$texy->htmlGenerator->registerHandler($this->solveDefList(...));
		$texy->htmlGenerator->registerHandler($this->solveDefItem(...));
	}


	public function beforeParse(string &$text): void
	{
		$RE = $REul = [];
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]) {
				$REul[] = $desc[0];
			}
		}

		$this->texy->registerBlockPattern(
			$this->parseList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $RE) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mU',
			'list',
		);

		$this->texy->registerBlockPattern(
			$this->parseDefList(...),
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)?   # modifier (1)
				( \S .{0,2000} )                      # definition term (2)
				: [ \t]*                              # colon separator
				' . Patterns::MODIFIER_H . '?         # modifier (3)
				\n
				([ \t]++)                             # indentation (4)
				(' . implode('|', $REul) . ')         # description marker (5)
				[ \t]*+
				\S .*                                 # content
			$~mU',
			'list/definition',
		);
	}


	/**
	 * Parses list.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseList(BlockParser $parser, array $matches, string $name, array $offsets): ?Texy\Nodes\ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$ordered = false;
		$bullet = null;
		$min = 1;
		$start = null;

		foreach ($this->bullets as $type => $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
				$bullet = $desc[3] ?? $desc[0];
				$min = isset($desc[3]) ? 2 : 1;
				$ordered = (bool) $desc[1];
				if ($ordered) {
					if ($type[0] === '1' && (int) $mBullet > 1) {
						$start = (int) $mBullet;
					} elseif ($type[0] === 'a' && $mBullet[0] > 'a') {
						$start = ord($mBullet[0]) - 96;
					} elseif ($type[0] === 'A' && $mBullet[0] > 'A') {
						$start = ord($mBullet[0]) - 64;
					}
				}
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$parser->moveBackward(1);

		$items = [];
		while ($item = $this->parseItem($parser, $bullet, false)) {
			$items[] = $item;
		}

		if (count($items) < $min) {
			return null;
		}

		return new Texy\Nodes\ListNode(
			$items,
			$ordered,
			$start,
			Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses definition list.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseDefList(
		BlockParser $parser,
		array $matches,
		string $name,
		array $offsets,
	): ?Texy\Nodes\DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->bullets as $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~A')) {
				$bullet = $desc[3] ?? $desc[0];
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$parser->moveBackward(2);

		$items = [];
		$patternTerm = '~^
			\n?
			( \S .* )                       # term content
			: [ \t]*                        # colon separator
			' . Patterns::MODIFIER_H . '?
		$~mUA';

		while (true) {
			if ($item = $this->parseItem($parser, $bullet, true)) {
				$items[] = new Texy\Nodes\DefinitionItemNode($item->content, false, $item->modifier);
				continue;
			}

			$termMatches = null;
			if ($parser->next($patternTerm, $termMatches)) {
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod);
				$termContent = $this->texy->createInlineParser()->parse($mContent);
				$items[] = new Texy\Nodes\DefinitionItemNode($termContent, true, $termMod);
				continue;
			}

			break;
		}

		return new Texy\Nodes\DefinitionListNode(
			$items,
			Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses single list item.
	 */
	private function parseItem(BlockParser $parser, string $bullet, bool $indented): ?Texy\Nodes\ListItemNode
	{
		$spacesBase = $indented ? ('[\ \t]{1,}') : '';
		$patternItem = "~^
			\\n?
			($spacesBase)                            # base indentation
			{$bullet}                                # bullet character
			[ \\t]*
			( \\S .* )?                              # content
			" . Patterns::MODIFIER_H . '?
		$~mAU';

		$matches = null;
		if (!$parser->next($patternItem, $matches)) {
			return null;
		}

		[, $mIndent, $mContent, $mMod] = $matches;
		// next lines
		$spaces = '';
		$content = ' ' . ($mContent ?? '');
		while ($parser->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)
		$~Am', $matches)) {
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			$content .= "\n" . $mBlank . $mContent;
		}

		// Parse content as blocks
		return new Texy\Nodes\ListItemNode(
			$this->texy->createBlockParser()->parse(trim($content)),
			Modifier::parse($mMod),
		);
	}


	public function solveList(Nodes\ListNode $node, Generator $generator): string
	{
		$tag = $node->ordered ? 'ol' : 'ul';
		$attrs = $generator->generateModifierAttrs($node->modifier);
		if ($node->start !== null && $node->start > 1) {
			$attrs .= ' start="' . $node->start . '"';
		}

		$items = [];
		foreach ($node->items as $item) {
			$items[] = $this->solveItem($item, $generator);
		}

		$nl = $this->texy->protect("\n", $this->texy::CONTENT_BLOCK);
		$open = $this->texy->protect("<{$tag}{$attrs}>", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("</{$tag}>", $this->texy::CONTENT_BLOCK);
		return $open . $nl . implode($nl, $items) . $nl . $close;
	}


	public function solveItem(Nodes\ListItemNode $node, Generator $generator): string
	{
		$attrs = $generator->generateModifierAttrs($node->modifier);

		// If first child is a simple ParagraphNode (no modifier), unwrap it
		$content = $node->content;
		if (
			count($content) === 1
			&& $content[0] instanceof Nodes\ParagraphNode
			&& $content[0]->modifier === null
		) {
			$innerContent = $generator->generateInlineContent($content[0]->content);
		} else {
			$innerContent = $generator->generateBlockContent($content);
		}

		$open = $this->texy->protect("<li{$attrs}>", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect('</li>', $this->texy::CONTENT_BLOCK);
		return $open . $innerContent . $close;
	}


	public function solveDefList(Nodes\DefinitionListNode $node, Generator $generator): string
	{
		$attrs = $generator->generateModifierAttrs($node->modifier);

		$items = [];
		foreach ($node->items as $item) {
			$items[] = $this->solveDefItem($item, $generator);
		}

		$open = $this->texy->protect("<dl{$attrs}>\n", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("\n</dl>", $this->texy::CONTENT_BLOCK);
		return $open . implode("\n", $items) . $close;
	}


	public function solveDefItem(Nodes\DefinitionItemNode $node, Generator $generator): string
	{
		$attrs = $generator->generateModifierAttrs($node->modifier);
		if ($node->term) {
			$content = $generator->generateInlineContent($node->content);
			$open = $this->texy->protect("<dt{$attrs}>", $this->texy::CONTENT_BLOCK);
			$close = $this->texy->protect('</dt>', $this->texy::CONTENT_BLOCK);
			return $open . $content . $close;
		} else {
			$content = $generator->generateBlockContent($node->content);
			$open = $this->texy->protect("<dd{$attrs}>", $this->texy::CONTENT_BLOCK);
			$close = $this->texy->protect('</dd>', $this->texy::CONTENT_BLOCK);
			return $open . $content . $close;
		}
	}
}
