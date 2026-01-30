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
use Texy\Position;
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
	 * @param  array<?int>  $offsets
	 */
	public function parseTable(ParseContext $context, array $matches, array $offsets): ?TableNode
	{
		if ($this->disableTables) {
			return null;
		}

		[, $mMod] = $matches;

		$startOffset = $offsets[0];

		$context->getBlockParser()->moveBackward();

		$rows = [];
		$isHead = false;
		/** @var array<int, array{node: TableCellNode, text: string}> $prevRow */
		$prevRow = [];
		$colModifier = [];
		$colCounter = 0;
		/** @var \SplObjectStorage<TableCellNode, array{text: string, offset: int}> $cellTexts */
		$cellTexts = new \SplObjectStorage;

		while (true) {
			$lineMatches = null;
			$lineOffsets = null;
			if ($context->getBlockParser()->next('~^ \| ([=-]) [+|=-]{2,} $~Um', $lineMatches, $lineOffsets)) {
				$isHead = !$isHead;
				$prevRow = [];
				continue;
			}

			if ($context->getBlockParser()->next('~^ ( \| ) (.*) (?: | \| [ \t]* ' . Patterns::MODIFIER_HV . '?)$~U', $lineMatches, $lineOffsets)) {
				// smarter head detection: if first row is followed by separator line, it's a head row
				if (count($rows) === 0 && !$isHead && $context->getBlockParser()->next('~^ \| [=-] [+|=-]{2,} $~Um', $foo)) {
					$isHead = true;
					$context->getBlockParser()->moveBackward();
				}

				[, , $mContent, $mRowMod] = $lineMatches;
				$lineBaseOffset = $lineOffsets[2] ?? $lineOffsets[0]; // offset of content after first |

				$cells = [];
				$originalContent = $mContent;
				$content = str_replace('\|', "\x13", $mContent);
				$content = Regexp::replace($content, '~(\[[^]]*)\|~', "$1\x13");

				$col = 0;
				$lastCell = null;
				$cellOffset = 0; // position within $content

				foreach (explode('|', $content) as $cellIndex => $cell) {
					$originalCell = $cell;
					$cell = strtr($cell, "\x13", '|');
					$cellAbsoluteOffset = $lineBaseOffset + $cellOffset;

					// rowSpan: ^ at end of cell or cell is just ^
					if (isset($prevRow[$col]) && ($m = Regexp::match($cell, '~\^[ \t]*$|\*??(.*)[ \t]+\^$~AU'))) {
						$prevRow[$col]['node']->rowspan++;
						$cellText = $m[1] ?? '';
						// Append text to the cell above
						$data = $cellTexts[$prevRow[$col]['node']];
						$data['text'] .= "\n" . $cellText;
						$cellTexts[$prevRow[$col]['node']] = $data;
						$col += $prevRow[$col]['node']->colspan;
						$lastCell = null;
						$cellOffset += strlen($originalCell) + 1; // +1 for |
						continue;
					}

					// colSpan: empty cell extends previous cell
					if ($cell === '' && $lastCell !== null) {
						$lastCell->colspan++;
						unset($prevRow[$col]);
						$col++;
						$cellOffset += strlen($originalCell) + 1;
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
					$~AU', captureOffset: true);

					if ($cellMatches) {
						$mHead = $cellMatches[1][0];
						$mModCol = $cellMatches[2][0];
						$mCellContent = $cellMatches[3][0];
						$mCellContentOffset = $cellMatches[3][1];
						$mCellMod = $cellMatches[4][0];

						$cellIsHeader = $isHead || ($mHead === '*');

						// column modifier inheritance
						if ($mModCol) {
							$colModifier[$col] = Modifier::parse($mModCol);
						}
						$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
						$cellMod->setProperties($mCellMod);

						// Calculate absolute offset of cell content
						$contentAbsoluteOffset = $cellAbsoluteOffset + $mCellContentOffset;

						// Create cell node - text will be parsed later
						$lastCell = new TableCellNode(new ContentNode, 1, 1, $cellIsHeader, $cellMod);
						$cells[] = $lastCell;
						$cellTexts[$lastCell] = ['text' => $mCellContent, 'offset' => $contentAbsoluteOffset];
						$prevRow[$col] = ['node' => $lastCell, 'text' => $mCellContent];
						$col++;
					}

					$cellOffset += strlen($originalCell) + 1; // +1 for |
				}

				// even up with empty cells
				while ($col < $colCounter) {
					$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
					$emptyCell = new TableCellNode(new ContentNode, 1, 1, $isHead, $cellMod);
					$cells[] = $emptyCell;
					$cellTexts[$emptyCell] = ['text' => '', 'offset' => 0];
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
					$data = $cellTexts[$cell];
					$text = rtrim((string) $data['text']);
					$baseOffset = $data['offset'];

					if (str_contains($text, "\n")) {
						// multiline - parse as block (disable nested tables)
						$this->disableTables = true;
						$cell->content->children = $context->parseBlock(Texy\Helpers::outdent($text), $baseOffset)->children;
						$this->disableTables = false;
					} else {
						// single line - parse as inline
						$trimmed = ltrim($text);
						$trimOffset = strlen($text) - strlen($trimmed);
						$cell->content->children = $context->parseInline($trimmed, $baseOffset + $trimOffset)->children;
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
			new Position($startOffset, strlen($matches[0])),
		);
	}
}
