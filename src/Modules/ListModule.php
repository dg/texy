<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Regexp, Syntax};
use Texy\Nodes\{DefinitionListNode, ListItemNode, ListNode, ListType};
use Texy\Parsing\ParseContext;
use function count, strlen;


/**
 * Processes ordered, unordered, and definition lists with nesting.
 */
final class ListModule extends Texy\Module
{
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		$re = $reUl = [];
		foreach ($this->config->listBullets as $desc) {
			$re[] = $desc[0];
			if (!$desc[1]->isOrdered()) {
				$reUl[] = $desc[0];
			}
		}

		$registry->addBlock(
			Syntax::List,
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)? # modifier (1)
				(' . implode('|', $re) . ')         # list marker (2)
				[ \t]*+
				\S .*                               # content
			$~mUx',
			$this->parseList(...),
		);

		$registry->addBlock(
			Syntax::DefinitionList,
			'~^
				(?:' . Patterns::MODIFIER_H . '\n)?   # modifier (1)
				( \S .{0,2000} )                      # definition term (2)
				: [ \t]*                              # colon separator
				' . Patterns::MODIFIER_H . '?         # modifier (3)
				\n
				([ \t]++)                             # indentation (4)
				(' . implode('|', $reUl) . ')         # description marker (5)
				[ \t]*+
				\S .*                                 # content
			$~mUx',
			$this->parseDefList(...),
		);
	}


	/**
	 * Parses list.
	 * @param  array{string, ?string, string}  $matches
	 */
	public function parseList(ParseContext $context, array $matches): ?ListNode
	{
		[, $mMod, $mBullet] = $matches;

		$bullet = null;
		$min = 1;
		$start = null;
		$listType = ListType::Unordered;

		foreach ($this->config->listBullets as $key => $desc) {
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
		);
	}


	/**
	 * Parses definition list.
	 * @param  array{string, ?string, string, ?string, string, string}  $matches
	 */
	public function parseDefList(ParseContext $context, array $matches): ?DefinitionListNode
	{
		[, $mMod, , , , $mBullet] = $matches;

		$bullet = null;

		foreach ($this->config->listBullets as $desc) {
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
			if ($context->getBlockParser()->next($patternTerm, $termMatches)) {
				/** @var array{string, string, ?string} $termMatches */
				[, $mContent, $mTermMod] = $termMatches;
				$termMod = Modifier::parse($mTermMod);
				$termContent = $context->parseInline($mContent);
				$items[] = new ListItemNode($termContent, true, $termMod);
				continue;
			}

			break;
		}

		return new DefinitionListNode(
			$items,
			Modifier::parse($mMod),
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
		if (!$context->getBlockParser()->next($patternItem, $matches)) {
			return null;
		}

		/** @var array{string, string, ?string, ?string} $matches */
		[, $mIndent, $mContent, $mMod] = $matches;
		$content = ' ' . ($mContent ?? '');

		// a bullet opening the first line is text, - * text is no nested list; the leading space keeps it so
		$isBullet = Regexp::match(
			$mContent ?? '',
			'~(?:' . implode('|', array_column($this->config->listBullets, 0)) . ')~Ax',
		) !== null;

		// next lines
		$spaces = '';
		while ($context->getBlockParser()->next('~^
			(\n*)
			' . Regexp::quote($mIndent) . '
			([ \t]{1,' . $spaces . '})
			(.*)                                     # content (3)
		$~Amx', $matches)) {
			/** @var array{string, string, string, string} $matches */
			[, $mBlank, $mSpaces, $mContent] = $matches;

			if ($spaces === '') {
				$spaces = strlen($mSpaces);
			}

			$content .= "\n" . $mBlank . $mContent;
		}

		return new ListItemNode(
			$context->parseBlock($isBullet ? rtrim($content) : trim($content)),
			false,
			Modifier::parse($mMod),
		);
	}
}
