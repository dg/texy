<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Range, Regexp, Syntax};
use Texy\Nodes\{ContentNode, TableCellNode, TableNode, TableRowNode};
use Texy\Parsing\{OffsetMap, ParseContext};
use function count, strlen;


/**
 * Table module.
 */
final class TableModule extends Texy\Module
{
	private bool $disableTables = false;


	public function register(Texy\Parsing\Engine $engine): void
	{
		$engine->addBlock(
			Syntax::Table,
			'~^
				(?:' . Patterns::ModifierHVAlign . '\n)? # modifier (1)
				\|                                       # table start
				.*                                       # content
			$~mUx',
			$this->parseTable(...),
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

		$caption = $this->parseCaption($context);

		$rows = [];
		$isHead = false;
		/** @var array<int, TableCellNode> $prevRow */
		$prevRow = [];
		$colModifier = [];
		$colCounter = 0;
		/** @var \SplObjectStorage<TableCellNode, list<array{content: string, offset: int}>> $cellTexts */
		$cellTexts = new \SplObjectStorage;

		$end = $startOffset + strlen($matches[0]);

		while (true) {
			$lineMatches = null;
			$lineOffsets = null;
			if ($context->getBlockParser()->next('~^ \| [=-] [+|=-]{2,} $~Umx', $lineMatches, $lineOffsets)) {
				$isHead = !$isHead;
				$prevRow = [];
				$end = $this->getLineEnd($lineMatches, $lineOffsets);
				continue;
			}

			if ($context->getBlockParser()
				->next('~^ \| (.*) (?: | \| [ \t]* ' . Patterns::ModifierHVAlign . '?)$~Ux', $lineMatches, $lineOffsets)) {
				// smarter head detection: if first row is followed by separator line, it's a head row
				if ($rows === [] && !$isHead && $context->getBlockParser()->next('~^ \| [=-] [+|=-]{2,} $~Umx', $foo)) {
					$isHead = true;
					$context->getBlockParser()->moveBackward();
				}

				// group 1 always participates in a successful match, but next() cannot type that
				$mContent = $lineMatches[1] ?? throw new \LogicException('Match without group 1.');
				$mRowMod = $lineMatches[2] ?? null;
				$lineBaseOffset = $lineOffsets[1] ?? throw new \LogicException('Match without group 1.'); // content after first |

				$cells = [];
				$col = 0;
				$lastCell = null;

				// a | divides cells unless it is escaped or stands inside [brackets]
				foreach (Regexp::split(
					$mContent,
					'~(?: \\\\\| | \[ [^]]*+ ] ) (*SKIP)(*FAIL) | \|~x',
					captureOffset: true,
				) as [$cell, $cellOffset]) {
					$cellAbsoluteOffset = $lineBaseOffset + $cellOffset;

					// rowSpan: ^ at end of cell or cell is just ^
					if (isset($prevRow[$col]) && ($m = Regexp::match($cell, '~\^[ \t]*$|\*??(.*)[ \t]+\^$~AU', captureOffset: true))) {
						$prevRow[$col]->rowspan++;
						/** @var non-empty-array<array{?string, int}> $m */
						$cellText = $m[1][0] ?? '';
						$cellTextOffset = ($m[1][1] ?? -1) >= 0 ? $cellAbsoluteOffset + $m[1][1] : $cellAbsoluteOffset;
						// Append text to the cell above
						$lines = $cellTexts[$prevRow[$col]];
						$lines[] = ['content' => (string) $cellText, 'offset' => $cellTextOffset];
						$cellTexts[$prevRow[$col]] = $lines;
						$col += $prevRow[$col]->colspan;
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
						( \*?? )                              # head mark (1)
						[ \t]*
						' . Patterns::ModifierHVAlign . '??   # modifier (2)
						(.*)                                  # content (3)
						' . Patterns::ModifierHVAlign . '?    # modifier (4)
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

						// column modifier inheritance; the modifiers are parsed unescaped, their ranges are of the source
						if ($mModCol) {
							$colModifier[$col] = new Modifier;
							$colModifier[$col]->setProperties(str_replace('\|', '|', $mModCol));
							$colModifier[$col]->range = $cellMatches[2][1] >= 0
								? new Range($cellAbsoluteOffset + $cellMatches[2][1], strlen($mModCol))
								: null;
						}
						$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
						$cellMod->setProperties($mCellMod === null ? null : str_replace('\|', '|', $mCellMod));
						if ($mCellMod !== null) { // the range covers the modifier only when it is written as a single part
							$cellMod->range = isset($colModifier[$col])
								? null
								: new Range($cellAbsoluteOffset + $cellMatches[4][1], strlen($mCellMod));
						} elseif (!$mModCol) { // inherited from the column, not written here
							$cellMod->range = null;
						}

						// Calculate absolute offset of cell content
						$contentAbsoluteOffset = $cellAbsoluteOffset + $mCellContentOffset;

						// Create cell node - text will be parsed later
						$lastCell = new TableCellNode(
							new ContentNode,
							1,
							1,
							$cellIsHeader,
							$cellMod,
							new Range($cellAbsoluteOffset, strlen($cell)),
						);
						$cells[] = $lastCell;
						$cellTexts[$lastCell] = [['content' => $mCellContent, 'offset' => $contentAbsoluteOffset]];
						$prevRow[$col] = $lastCell;
						$col++;
					}
				}

				// even up with empty cells
				while ($col < $colCounter) {
					$cellMod = isset($colModifier[$col]) ? clone $colModifier[$col] : new Modifier;
					$emptyCell = new TableCellNode(new ContentNode, 1, 1, $isHead, $cellMod);
					$cells[] = $emptyCell;
					$cellTexts[$emptyCell] = [['content' => '', 'offset' => 0]];
					$prevRow[$col] = $emptyCell;
					$col++;
				}

				$colCounter = $col;
				$end = $this->getLineEnd($lineMatches, $lineOffsets);

				if ($cells) {
					$rowMod = Modifier::parse($mRowMod, $lineOffsets[2] ?? null);
					$rowOffset = $lineOffsets[0] ?? throw new \LogicException('Match without group 0.');
					$rows[] = new TableRowNode($cells, $isHead, $rowMod, new Range($rowOffset, $end - $rowOffset));
				} else {
					// redundant row - decrement rowspan
					foreach ($prevRow as $cell) {
						$cell->rowspan--;
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
						[$outdented, $map] = OffsetMap::outdentLines($lines);
						[$outdented, $escapes] = self::unescapePipes($outdented);
						$this->disableTables = true;
						try {
							$parsed = $context->parseBlock($outdented);
						} finally {
							$this->disableTables = false;
						}
						$escapes?->applyTo($parsed);
						$map->applyTo($parsed);
					} else {
						// single line - parse as inline
						$text = $lines[0]['content'] ?? '';
						$trimmed = ltrim($text);
						$trimOffset = strlen($text) - strlen($trimmed);
						$baseOffset = ($lines[0]['offset'] ?? 0) + $trimOffset;
						[$trimmed, $escapes] = self::unescapePipes($trimmed, $baseOffset);
						$parsed = $context->parseInline($trimmed, $escapes ? 0 : $baseOffset);
						$escapes?->applyTo($parsed);
					}

					$cell->content->children = $parsed->children;

					// empty cell gets &nbsp;
					if ($cell->content->children === []) {
						$cell->content->children = [new Texy\Nodes\TextNode("\u{A0}")];
					}
				}
			}
		}

		return new TableNode(
			$rows,
			Modifier::parse($mMod, $offsets[1]),
			new Range($startOffset, $end - $startOffset),
			$caption,
		);
	}


	/**
	 * Replaces \| with | and maps offsets in the result to the source, where $text starts at $offset.
	 * @return array{string, ?OffsetMap}  the map is null when nothing was escaped
	 */
	private static function unescapePipes(string $text, int $offset = 0): array
	{
		$parts = explode('\|', $text);
		$last = array_pop($parts);
		if (!$parts) {
			return [$text, null];
		}

		$map = [0 => $offset];
		$local = 0;
		foreach ($parts as $part) {
			$local += strlen($part);
			$offset += strlen($part) + 1;
			$map[$local] = $offset; // the | written behind a backslash
			$local++;
			$offset++;
		}

		return [implode('|', $parts) . '|' . $last, new OffsetMap($map)];
	}


	/**
	 * Returns the source offset where a line matched by the block parser ends.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	private function getLineEnd(array $matches, array $offsets): int
	{
		// group 0 always participates in a successful match, but next() cannot type that
		$offset = $offsets[0] ?? throw new \LogicException('Match without group 0.');
		return $offset + strlen($matches[0] ?? '');
	}


	/** Parses the optional caption line |## Text preceding the first row. */
	private function parseCaption(ParseContext $context): ?ContentNode
	{
		$matches = $offsets = null;
		$context->getBlockParser()->next(
			'~^
				\|                            # opening pipe
				( [\#=] ){2,}  (?! [|\#=+] )  # opening chars (1)
				(.+)                          # caption (2)
				\1*                           # matching closing chars
				\|? \ *                       # optional closing pipe and spaces
			$~Umx',
			$matches,
			$offsets,
		);

		if (!isset($matches[2], $offsets[2])) {
			return null;
		}

		// space after the opening chars is a separator, not content
		$text = rtrim($matches[2]);
		$trimmed = ltrim($text);
		$offset = $offsets[2] + strlen($text) - strlen($trimmed);

		return $context->parseInline($trimmed, $offset);
	}
}
