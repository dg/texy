<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\HtmlElement;
use Texy\Modifier;
use Texy\Patterns;
use Texy\Regexp;


/**
 * Table module.
 */
final class TableModule extends Texy\Module
{
	/** @deprecated */
	public $oddClass;

	/** @deprecated */
	public $evenClass;

	private $disableTables;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->registerBlockPattern(
			[$this, 'patternTable'],
			'#^(?:' . Patterns::MODIFIER_HV . '\n)?' // .{color: red}
			. '\|.*()$#mU', // | ....
			'table'
		);
	}


	/**
	 * Callback for:.
	 *
	 * .(title)[class]{style}>
	 * |------------------
	 * | xxx | xxx | xxx | .(..){..}[..]
	 * |------------------
	 * | aa | bb | cc |
	 *
	 * @return HtmlElement|string|null
	 */
	public function patternTable(Texy\BlockParser $parser, array $matches)
	{
		if ($this->disableTables) {
			return null;
		}
		[, $mMod] = $matches;
		// [1] => .(title)[class]{style}<>_

		$texy = $this->texy;

		$el = new HtmlElement('table');
		$mod = new Modifier($mMod);
		$mod->decorate($texy, $el);

		$parser->moveBackward();

		if ($parser->next('#^\|(\#|\=){2,}(?![|\#=+])(.+)\1*\|? *' . Patterns::MODIFIER_H . '?()$#Um', $matches)) {
			[, , $mContent, $mMod] = $matches;
			// [1] => # / =
			// [2] => ....
			// [3] => .(title)[class]{style}<>

			$caption = $el->create('caption');
			$mod = new Modifier($mMod);
			$mod->decorate($texy, $caption);
			$caption->parseLine($texy, $mContent);
		}

		$isHead = false;
		$colModifier = [];
		$prevRow = []; // rowSpan building helper
		$rowCounter = 0;
		$colCounter = 0;
		$elPart = null;

		while (true) {
			if ($parser->next('#^\|([=-])[+|=-]{2,}$#Um', $matches)) { // line
				$isHead = !$isHead;
				$prevRow = [];
				continue;
			}

			if ($parser->next('#^\|(.*)(?:|\|\ *' . Patterns::MODIFIER_HV . '?)()$#U', $matches)) {
				// smarter head detection
				if ($rowCounter === 0 && !$isHead && $parser->next('#^\|[=-][+|=-]{2,}$#Um', $foo)) {
					$isHead = true;
					$parser->moveBackward();
				}

				if ($elPart === null) {
					$elPart = $el->create($isHead ? 'thead' : 'tbody');

				} elseif (!$isHead && $elPart->getName() === 'thead') {
					$this->finishPart($elPart);
					$elPart = $el->create('tbody');
				}

				[, $mContent, $mMod] = $matches;
				// [1] => ....
				// [2] => .(title)[class]{style}<>_

				$elRow = $this->processRow($mContent, $mMod, $isHead, $texy, $prevRow, $colModifier, $colCounter, $rowCounter);

				if ($elRow->count()) {
					$elPart->add($elRow);
					$rowCounter++;
				} else { // redundant row
					foreach ($prevRow as $elCell) {
						$elCell->rowSpan--;
					}
				}
				continue;
			}

			break;
		}

		if ($elPart === null) { // invalid table
			return null;
		}

		if ($elPart->getName() === 'thead') {
			// thead is optional, tbody is required
			$elPart->setName('tbody');
		}

		$this->finishPart($elPart);

		// event listener
		$texy->invokeHandlers('afterTable', [$parser, $el, $mod]);

		return $el;
	}


	private function processRow(
		string $content,
		string $mMod,
		bool $isHead,
		Texy\Texy $texy,
		array &$prevRow,
		array &$colModifier,
		int &$colCounter,
		int $rowCounter
	): HtmlElement {
		$elRow = new HtmlElement('tr');
		$mod = new Modifier($mMod);
		$mod->decorate($texy, $elRow);

		$rowClass = $rowCounter % 2 === 0 ? $this->oddClass : $this->evenClass;
		if ($rowClass && !isset($mod->classes[$this->oddClass]) && !isset($mod->classes[$this->evenClass])) {
			$elRow->attrs['class'][] = $rowClass;
		}

		$col = 0;
		$elCell = null;

		// special escape sequence \|
		$content = str_replace('\|', "\x13", $content);
		$content = Regexp::replace($content, '#(\[[^\]]*)\|#', "$1\x13"); // HACK: support for [..|..]

		foreach (explode('|', $content) as $cell) {
			$cell = strtr($cell, "\x13", '|');
			// rowSpan
			if (isset($prevRow[$col]) && ($matches = Regexp::match($cell, '#\^\ *$|\*??(.*)\ +\^$#AU'))) {
				$prevRow[$col]->rowSpan++;
				$cell = $matches[1] ?? '';
				$prevRow[$col]->text .= "\n" . $cell;
				$col += $prevRow[$col]->colSpan;
				$elCell = null;
				continue;
			}

			// colSpan
			if ($cell === '' && $elCell) {
				$elCell->colSpan++;
				unset($prevRow[$col]);
				$col++;
				continue;
			}

			// common cell
			if ($elCell = $this->processCell($cell, $colModifier[$col], $isHead, $texy)) {
				$elRow->add($elCell);
				$prevRow[$col] = $elCell;
				$col++;
			}
		}

		// even up with empty cells
		while ($col < $colCounter) {
			$elCell = new TableCellElement;
			$elCell->setName($isHead ? 'th' : 'td');
			if (isset($colModifier[$col])) {
				$colModifier[$col]->decorate($texy, $elCell);
			}
			$elRow->add($elCell);
			$prevRow[$col] = $elCell;
			$col++;
		}
		$colCounter = $col;
		return $elRow;
	}


	private function processCell(string $cell, ?Modifier &$cellModifier, bool $isHead, Texy\Texy $texy): ?TableCellElement
	{
		$matches = Regexp::match($cell, '#(\*??)\ *' . Patterns::MODIFIER_HV . '??(.*)' . Patterns::MODIFIER_HV . '?\ *()$#AU');
		if (!$matches) {
			return null;
		}
		[, $mHead, $mModCol, $mContent, $mMod] = $matches;
		// [1] => * ^
		// [2] => .(title)[class]{style}<>_
		// [3] => ....
		// [4] => .(title)[class]{style}<>_

		if ($mModCol) {
			$cellModifier = new Modifier($mModCol);
		}
		$mod = $cellModifier ? clone $cellModifier : new Modifier;
		$mod->setProperties($mMod);

		$elCell = new TableCellElement;
		$elCell->setName($isHead || ($mHead === '*') ? 'th' : 'td');
		$mod->decorate($texy, $elCell);
		$elCell->text = $mContent;
		return $elCell;
	}


	/**
	 * Parse text in all cells.
	 */
	private function finishPart(HtmlElement $elPart): void
	{
		foreach ($elPart->getChildren() as $elRow) {
			foreach ($elRow->getChildren() as $elCell) {
				if ($elCell->colSpan > 1) {
					$elCell->attrs['colspan'] = $elCell->colSpan;
				}

				if ($elCell->rowSpan > 1) {
					$elCell->attrs['rowspan'] = $elCell->rowSpan;
				}

				$text = rtrim((string) $elCell->text);
				if (strpos($text, "\n") !== false) {
					// multiline parse as block
					// HACK: disable tables
					$this->disableTables = true;
					$elCell->parseBlock($this->texy, Texy\Helpers::outdent($text));
					$this->disableTables = false;
				} else {
					$elCell->parseLine($this->texy, ltrim($text));
				}

				if ($elCell->getText() === '') {
					$elCell->setText("\u{A0}"); // &nbsp;
				}
			}
		}
	}
}
