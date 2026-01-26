<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Patterns;
use Texy\Position;
use Texy\Regexp;
use function implode, ltrim, strlen;


/**
 * Table module.
 */
final class TableModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->htmlGenerator->registerHandler($this->solveTable(...));
		$texy->htmlGenerator->registerHandler($this->solveRow(...));
		$texy->htmlGenerator->registerHandler($this->solveCell(...));
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parseTable(...),
			'~^
				(?:' . Patterns::MODIFIER_HV . '\n)? # modifier (1)
				\|                                   # table start
				.*                                   # content
			$~mU',
			'table',
		);
	}


	/**
	 * Parses tables.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseTable(
		Texy\BlockParser $parser,
		array $matches,
		string $name,
		array $offsets,
	): ?Texy\Nodes\TableNode
	{
		[, $mMod] = $matches;

		$startOffset = $offsets[0];

		$parser->moveBackward();

		$rows = [];
		$isHead = false;

		while (true) {
			$lineMatches = null;
			if ($parser->next('~^ \| ([=-]) [+|=-]{2,} $~Um', $lineMatches)) {
				$isHead = !$isHead;
				continue;
			}

			if ($parser->next('~^ \| (.*) (?: | \| [ \t]* ' . Patterns::MODIFIER_HV . '?)$~U', $lineMatches)) {
				[, $mContent, $mRowMod] = $lineMatches;
				$rowMod = Modifier::parse($mRowMod); // TODO: currently unused

				$cells = [];
				$content = str_replace('\|', "\x13", $mContent);
				$content = Regexp::replace($content, '~(\[[^]]*)\|~', "$1\x13");

				foreach (explode('|', $content) as $cell) {
					$cell = strtr($cell, "\x13", '|');
					$cellMatches = Regexp::match($cell, '~
						( \*?? )                          # head mark (1)
						[ \t]*
						' . Patterns::MODIFIER_HV . '??   # modifier (2)
						(.*)                              # content (3)
						' . Patterns::MODIFIER_HV . '?    # modifier (4)
						[ \t]*
					$~AU');

					if ($cellMatches) {
						[, $mHead, , $mCellContent, $mCellMod] = $cellMatches;
						$cellIsHeader = $isHead || ($mHead === '*');
						$cellContent = $this->texy->createInlineParser()->parse(ltrim($mCellContent));
						$cells[] = new Texy\Nodes\TableCellNode($cellContent, 1, 1, $cellIsHeader, Modifier::parse($mCellMod));
					}
				}

				if ($cells) {
					$rows[] = new Texy\Nodes\TableRowNode($cells);
				}

				continue;
			}

			break;
		}

		if (!$rows) {
			return null;
		}

		return new Texy\Nodes\TableNode(
			$rows,
			Modifier::parse($mMod),
			new Position($startOffset, strlen($matches[0])),
		);
	}


	public function solveTable(Nodes\TableNode $node, Generator $generator): string
	{
		$attrs = $generator->generateModifierAttrs($node->modifier);

		$rows = [];
		foreach ($node->rows as $row) {
			$rows[] = $this->solveRow($row, $generator);
		}

		$open = $this->texy->protect("<table{$attrs}>\n", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("\n</table>", $this->texy::CONTENT_BLOCK);
		return $open . implode("\n", $rows) . $close;
	}


	public function solveRow(Nodes\TableRowNode $node, Generator $generator): string
	{
		$cells = [];
		foreach ($node->cells as $cell) {
			$cells[] = $this->solveCell($cell, $generator);
		}

		$open = $this->texy->protect('<tr>', $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect('</tr>', $this->texy::CONTENT_BLOCK);
		return $open . implode('', $cells) . $close;
	}


	public function solveCell(Nodes\TableCellNode $node, Generator $generator): string
	{
		$tag = $node->isHeader ? 'th' : 'td';
		$attrs = $generator->generateModifierAttrs($node->modifier);

		if ($node->colspan > 1) {
			$attrs .= ' colspan="' . $node->colspan . '"';
		}
		if ($node->rowspan > 1) {
			$attrs .= ' rowspan="' . $node->rowspan . '"';
		}

		$content = $generator->generateInlineContent($node->content);
		$open = $this->texy->protect("<{$tag}{$attrs}>", $this->texy::CONTENT_BLOCK);
		$close = $this->texy->protect("</{$tag}>", $this->texy::CONTENT_BLOCK);
		return $open . $content . $close;
	}
}
