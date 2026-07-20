<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\TableCellNode;
use Texy\Nodes\TableNode;
use Texy\Nodes\TableRowNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Range;
use Texy\Regexp;
use Texy\Syntax;
use function array_pop, count, ltrim, rtrim, strlen;


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
			$~mUx',
			Syntax::Table,
		);
	}


	/**
	 * Parses tables.
	 * @param  array{string, ?string}  $matches
	 * @param  array{int, ?int}  $offsets
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
		/** @var \SplObjectStorage<TableCellNode, list<array{content: string, offset: int}>> $cellTexts */
		$cellTexts = new \SplObjectStorage;

		while (true) {
			$lineMatches = null;
			$lineOffsets = null;
			if ($context->getBlockParser()->next('~^ \| ([=-]) [+|=-]{2,} $~Umx', $lineMatches, $lineOffsets)) {
				$isHead = !$isHead;
				$prevRow = [];
				continue;
			}

			if ($context->getBlockParser()->next('~^ ( \| ) (.*) (?: | \| [ \t]* ' . Patterns::MODIFIER_HV . '?)$~Ux', $lineMatches, $lineOffsets)) {
				// smarter head detection: if first row is followed by separator line, it's a head row
				if (count($rows) === 0 && !$isHead && $context->getBlockParser()->next('~^ \| [=-] [+|=-]{2,} $~Umx', $foo)) {
					$isHead = true;
					$context->getBlockParser()->moveBackward();
				}

				// groups 1 and 2 always participate in a successful match, but next() cannot type that
				$mContent = $lineMatches[2] ?? throw new \LogicException('Match without group 2.');
				$mRowMod = $lineMatches[3] ?? null;
				$lineBaseOffset = $lineOffsets[2] ?? throw new \LogicException('Match without group 2.'); // content after first |

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
					if (isset($prevRow[$col]) && ($m = Regexp::match($cell, '~\^[ \t]*$|\*??(.*)[ \t]+\^$~AU', captureOffset: true))) {
						$prevRow[$col]['node']->rowspan++;
						/** @var non-empty-array<array{?string, int}> $m */
						$cellText = $m[1][0] ?? '';
						$cellTextOffset = ($m[1][1] ?? -1) >= 0 ? $cellAbsoluteOffset + $m[1][1] : $cellAbsoluteOffset;
						// Append text to the cell above
						$lines = $cellTexts[$prevRow[$col]['node']];
						$lines[] = ['content' => (string) $cellText, 'offset' => $cellTextOffset];
						$cellTexts[$prevRow[$col]['node']] = $lines;
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
					$~AUx', captureOffset: true);

					if ($cellMatches) {
						$mHead = $cellMatches[1][0];
						$mModCol = $cellMatches[2][0];
						// group 3 is (.*), so it always participates
						$mCellContent = $cellMatches[3][0] ?? throw new \LogicException('Cell without group 3.');
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
						$cellTexts[$lastCell] = [['content' => $mCellContent, 'offset' => $contentAbsoluteOffset]];
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
					$cellTexts[$emptyCell] = [['content' => '', 'offset' => 0]];
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
					$lines = $cellTexts[$cell];

					// right-trim: drop blank trailing fragments, trim the last one
					while ($lines && rtrim($lines[count($lines) - 1]['content']) === '') {
						array_pop($lines);
					}
					if ($lines) {
						$last = count($lines) - 1;
						$lines[$last]['content'] = rtrim($lines[$last]['content']);
					}

					if (count($lines) > 1) {
						// multiline - parse as block (disable nested tables)
						[$outdented, $map] = Texy\OffsetMap::outdentLines($lines);
						$this->disableTables = true;
						try {
							$parsed = $context->parseBlock($outdented);
						} finally {
							$this->disableTables = false;
						}
						$map->applyTo($parsed);
						$cell->content->children = $parsed->children;
					} else {
						// single line - parse as inline
						$text = $lines[0]['content'] ?? '';
						$trimmed = ltrim($text);
						$trimOffset = strlen($text) - strlen($trimmed);
						$baseOffset = ($lines[0]['offset'] ?? 0) + $trimOffset;
						$cell->content->children = $context->parseInline($trimmed, $baseOffset)->children;
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
			new Range($startOffset, strlen($matches[0])),
		);
	}
}
