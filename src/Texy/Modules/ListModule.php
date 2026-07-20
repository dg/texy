<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\DefinitionListNode;
use Texy\Nodes\ListItemNode;
use Texy\Nodes\ListNode;
use Texy\Nodes\ListType;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Range;
use Texy\Regexp;
use Texy\Syntax;
use function count, implode, ord, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	/** @var array<string, array{string, ListType, 2?: string}> [regex, type, next-regex?] */
	public array $bullets = [
		'*' => ['\* [ \t]', ListType::Unordered],
		'-' => ['[\x{2013}-] (?! [>-] )', ListType::Unordered],
		'+' => ['\+ [ \t]', ListType::Unordered],
		'1.' => ['1 \. [ \t]' /* not \d! */, ListType::Decimal, '\d{1,3} \. [ \t]'],
		'1)' => ['\d{1,3} \) [ \t]', ListType::Decimal],
		'I.' => ['I \. [ \t]', ListType::UpperRoman, '[IVX]{1,4} \. [ \t]'],
		'I)' => ['[IVX]+ \) [ \t]', ListType::UpperRoman], // before A) !
		'a)' => ['[a-z] \) [ \t]', ListType::LowerAlpha],
		'A)' => ['[A-Z] \) [ \t]', ListType::UpperAlpha],
	];


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::List] = true;
		$texy->allowed[Syntax::DefinitionList] = true;
	}


	public function beforeParse(string &$text): void
	{
		$RE = $REul = [];
		foreach ($this->bullets as $desc) {
			$RE[] = $desc[0];
			if (!$desc[1]->isOrdered()) {
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
			$~mUx',
			Syntax::List,
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
			$~mUx',
			Syntax::DefinitionList,
		);
	}


	/**
	 * Parses list.
	 * @param  array{string, ?string, string}  $matches
	 * @param  array{int, ?int, int}  $offsets
	 */
	public function parseList(ParseContext $context, array $matches, array $offsets): ?ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$bullet = null;
		$min = 1;
		$start = null;
		$listType = ListType::Unordered;

		foreach ($this->bullets as $key => $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~Ax')) {
				$bullet = $desc[2] ?? $desc[0];
				$min = isset($desc[2]) ? 2 : 1;
				$listType = $desc[1];
				if ($listType->isOrdered()) {
					if ($key[0] === '1' && (int) $mBullet > 1) {
						$start = (int) $mBullet;
					} elseif ($key[0] === 'a' && $mBullet[0] > 'a') {
						$start = ord($mBullet[0]) - 96;
					} elseif ($key[0] === 'A' && $mBullet[0] > 'A') {
						$start = ord($mBullet[0]) - 64;
					}
				}
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$context->getBlockParser()->moveBackward();

		$items = [];
		while ($item = $this->parseItem($context, $bullet, false)) {
			$items[] = $item;
		}

		if (count($items) < $min) {
			return null;
		}

		return new ListNode(
			$items,
			$listType,
			$start,
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses definition list.
	 * @param  array{string, ?string, string, ?string, string, string}  $matches
	 * @param  array{int, ?int, int, ?int, int, int}  $offsets
	 */
	public function parseDefList(ParseContext $context, array $matches, array $offsets): ?DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->bullets as $desc) {
			if (Regexp::match($mBullet, '~' . $desc[0] . '~Ax')) {
				$bullet = $desc[2] ?? $desc[0];
				break;
			}
		}

		if ($bullet === null) {
			return null;
		}

		$context->getBlockParser()->moveBackward(2);

		$items = [];
		$patternTerm = '~^
			\n?
			( \S .* )                       # term content
			: [ \t]*                        # colon separator
			' . Patterns::MODIFIER_H . '?
		$~mUAx';

		while (true) {
			if ($item = $this->parseItem($context, $bullet, true)) {
				$items[] = $item;
				continue;
			}

			$termMatches = null;
			$termOffsets = null;
			if ($context->getBlockParser()->next($patternTerm, $termMatches, $termOffsets)) {
				/** @var array{string, string, ?string} $termMatches */
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod);
				// group 1 always participates in a successful match, but next() cannot type that
				$contentOffset = $termOffsets[1] ?? throw new \LogicException('Match without group 1.');
				$termContent = $context->parseInline($mContent, $contentOffset);
				$items[] = new ListItemNode($termContent, true, $termMod);
				continue;
			}

			break;
		}

		return new DefinitionListNode(
			$items,
			Modifier::parse($mMod),
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses single list item.
	 */
	private function parseItem(ParseContext $context, string $bullet, bool $indented): ?ListItemNode
	{
		$spacesBase = $indented ? ('[\ \t]{1,}') : '';
		$patternItem = "~^
			\\n?
			($spacesBase)                            # base indentation (1)
			{$bullet}                                # bullet character
			[ \\t]*
			( \\S .* )?                              # content (2)
			" . Patterns::MODIFIER_H . '?           # modifier (3)
		$~mAUx';

		$matches = null;
		$offsets = null;
		if (!$context->getBlockParser()->next($patternItem, $matches, $offsets)) {
			return null;
		}

		/** @var array{string, string, ?string, ?string} $matches */
		[, $mIndent, $mContent, $mMod] = $matches;

		// Assemble content and map local line starts to absolute source offsets
		$map = [];
		$content = ' ' . ($mContent ?? '');
		if ($mContent !== null) {
			// content matched, so group 2 participated and carries an offset
			$map[1] = $offsets[2] ?? throw new \LogicException('Content without group 2.'); // 1 = the leading space
		}

		// next lines
		$spaces = '';
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Amx', $matches, $offsets)) {
			/** @var array{string, string, string, string} $matches */
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			if ($mContent !== '' && $offsets[3] !== null) {
				$map[strlen($content) + 1 + strlen($mBlank)] = $offsets[3]; // 1 = "\n"
			}

			$content .= "\n" . $mBlank . $mContent;
		}

		// Parse content as blocks
		$parsed = $context->parseBlock(trim($content));

		// Fix positions in parsed content using offset mapping
		if ($map) {
			$skipped = strlen($content) - strlen(ltrim($content));
			(new Texy\OffsetMap($map, $skipped))->applyTo($parsed);
		}

		return new ListItemNode(
			$parsed,
			false,
			Modifier::parse($mMod),
		);
	}
}
