<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\TableCellNode;
use Texy\Nodes\TableNode;
use Texy\Nodes\TableRowNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Regexp;
use Texy\Syntax;
use function count, ltrim, rtrim, str_contains, strlen;


/**
 * Table module.
 */
final class TableModule extends Texy\Module
{
	private bool $disableTables = false;


	public function __construct(
		private Texy\Texy $texy,
	) {
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
			Syntax::Table,
		);
	}


	/**
	 * Parses tables.
	 * @param  array<?string>  $matches
	 */
	public function parseTable(ParseContext $context, array $matches): ?TableNode
	{
		if ($this->disableTables) {
			return null;
		}

		[, $mMod] = $matches;

		$context->getBlockParser()->moveBackward();

		$rows = [];
		$isHead = false;
		/** @var array<int, array{node: TableCellNode, text: string}> $prevRow */
		$prevRow = [];
		$colModifier = [];
		$colCounter = 0;
		/** @var \SplObjectStorage<TableCellNode, string> $cellTexts */
		$cellTexts = new \SplObjectStorage;

		while (true) {
			$lineMatches = null;
			if ($context->getBlockParser()->next('~^ \| ([=-]) [+|=-]{2,} $~Um', $lineMatches)) {
				$isHead = !$isHead;
				$prevRow = [];
				continue;
			}

			if ($context->getBlockParser()->next('~^ ( \| ) (.*) (?: | \| [ \t]* ' . Patterns::MODIFIER_HV . '?)$~U', $lineMatches)) {
				// smarter head detection: if first row is followed by separator line, it's a head row
				if (count($rows) === 0 && !$isHead && $context->getBlockParser()->next('~^ \| [=-] [+|=-]{2,} $~Um', $foo)) {
					$isHead = true;
					$context->getBlockParser()->moveBackward();
				}

				[, , $mContent, $mRowMod] = $lineMatches;

				$cells = [];
				$content = str_replace('\|', "\x13", $mContent);
				$content = Regexp::replace($content, '~(\[[^]]*)\|~', "$1\x13");

				$col = 0;
				$lastCell = null;

				foreach (explode('|', $content) as $cell) {
					$cell = strtr($cell, "\x13", '|');

					// rowSpan: ^ at end of cell or cell is just ^
					if (isset($prevRow[$col]) && ($m = Regexp::match($cell, '~\^[ \t]*$|\*??(.*)[ \t]+\^$~AU'))) {
						$prevRow[$col]['node']->rowspan++;
						$cellText = $m[1] ?? '';
						// Append text to the cell above
						$cellTexts[$prevRow[$col]['node']] .= "\n" . $cellText;
						$col += $prevRow[$col]['node']->colspan;
						$lastCell = null;
						continue;
					}

					// colSpan: empty cell extends previous cell
					if ($cell === '' && $lastCell !== null) {
						$lastCell->colspan++;
						unset($prevRow[$col]);
						$col++;
						continue;
					}

					// common cell
					$cellMatches = Regexp::match($cell, '~
						( \*?? )                          # head mark (1)
						[ \t]*
						' . Patterns::MODIFIER_HV . '??   # modifier (2)
						(.*)                              # content (3)
						' . Patterns::MODIFIER_HV . '?    # modifier (4)
						[ \t]*
					$~AU');

					if ($cellMatches) {
						$mHead = $cellMatches[1];
						$mModCol = $cellMatches[2];
						$mCellContent = $cellMatches[3];
						$mCellMod = $cellMatches[4];

						$cellIsHeader = $isHead || ($mHead === '*');

						// column modifier inheritance
						if ($mModCol) {
							$colModifier[$col] = Modifier::parse($mModCol);
						}
						$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
						$cellMod->setProperties($mCellMod);

						// Create cell node - text will be parsed later
						$lastCell = new TableCellNode(new ContentNode, 1, 1, $cellIsHeader, $cellMod);
						$cells[] = $lastCell;
						$cellTexts[$lastCell] = $mCellContent ?? '';
						$prevRow[$col] = ['node' => $lastCell, 'text' => $mCellContent];
						$col++;
					}
				}

				// even up with empty cells
				while ($col < $colCounter) {
					$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
					$emptyCell = new TableCellNode(new ContentNode, 1, 1, $isHead, $cellMod);
					$cells[] = $emptyCell;
					$cellTexts[$emptyCell] = '';
					$prevRow[$col] = ['node' => $emptyCell, 'text' => ''];
					$col++;
				}

				$colCounter = $col;

				if ($cells) {
					$rowMod = Modifier::parse($mRowMod);
					$rows[] = new TableRowNode($cells, $isHead, $rowMod);
				} else {
					// redundant row - decrement rowspan
					foreach ($prevRow as $item) {
						$item['node']->rowspan--;
					}
				}

				continue;
			}

			break;
		}

		if (!$rows) {
			return null;
		}

		// Parse cell text content after rowspan/colspan is determined
		foreach ($rows as $row) {
			foreach ($row->cells as $cell) {
				if (isset($cellTexts[$cell])) {
					$text = rtrim((string) $cellTexts[$cell]);

					if (str_contains($text, "\n")) {
						// multiline - parse as block (disable nested tables)
						$this->disableTables = true;
						$cell->content->children = $context->parseBlock(Texy\Helpers::outdent($text))->children;
						$this->disableTables = false;
					} else {
						// single line - parse as inline
						$cell->content->children = $context->parseInline(ltrim($text))->children;
					}

					// empty cell gets &nbsp;
					if ($cell->content->children === []) {
						$cell->content->children = [new Texy\Nodes\TextNode("\u{A0}")];
					}
				}
			}
		}

		return new TableNode(
			$rows,
			Modifier::parse($mMod),
		);
	}
}
