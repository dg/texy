<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Table module.
 *
 * @author     David Grudl
 */
final class TableModule extends Texy\Module
{
	/** @var string  CSS class for odd rows */
	public $oddClass;

	/** @var string  CSS class for even rows */
	public $evenClass;

	private $disableTables;


	public function __construct($texy)
	{
		$this->texy = $texy;

		$texy->registerBlockPattern(
			array($this, 'patternTable'),
			'#^(?:'.Texy\Patterns::MODIFIER_HV.'\n)?' // .{color: red}
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
	 * @param  Texy\BlockParser
	 * @param  array      regexp matches
	 * @param  string     pattern name
	 * @return Texy\HtmlElement|string|FALSE
	 */
	public function patternTable($parser, $matches)
	{
		if ($this->disableTables) {
			return FALSE;
		}
		list(, $mMod) = $matches;
		// [1] => .(title)[class]{style}<>_

		$tx = $this->texy;

		$el = Texy\HtmlElement::el('table');
		$mod = new Texy\Modifier($mMod);
		$mod->decorate($tx, $el);

		$parser->moveBackward();

		if ($parser->next('#^\|(\#|\=){2,}(?![|\#=+])(.+)\\1*\|? *'.Texy\Patterns::MODIFIER_H.'?()$#Um', $matches)) {
			list(, , $mContent, $mMod) = $matches;
			// [1] => # / =
			// [2] => ....
			// [3] => .(title)[class]{style}<>

			$caption = $el->create('caption');
			$mod = new Texy\Modifier($mMod);
			$mod->decorate($tx, $caption);
			$caption->parseLine($tx, $mContent);
		}

		$isHead = FALSE;
		$colModifier = array();
		$prevRow = array(); // rowSpan building helper
		$rowCounter = 0;
		$colCounter = 0;
		$elPart = NULL;
		$lineMode = FALSE; // rows must be separated by lines

		while (TRUE) {
			if ($parser->next('#^\|([=-])[+|=-]{2,}$#Um', $matches)) { // line
				if ($lineMode) {
					if ($matches[1] === '=') {
						$isHead = !$isHead;
					}
				} else {
					$isHead = !$isHead;
					$lineMode = $matches[1] === '=';
				}
				$prevRow = array();
				continue;
			}

			if ($parser->next('#^\|(.*)(?:|\|\ *'.Texy\Patterns::MODIFIER_HV.'?)()$#U', $matches)) {
				// smarter head detection
				if ($rowCounter === 0 && !$isHead && $parser->next('#^\|[=-][+|=-]{2,}$#Um', $foo)) {
					$isHead = TRUE;
					$parser->moveBackward();
				}

				if ($elPart === NULL) {
					$elPart = $el->create($isHead ? 'thead' : 'tbody');

				} elseif (!$isHead && $elPart->getName() === 'thead') {
					$this->finishPart($elPart);
					$elPart = $el->create('tbody');
				}


				// PARSE ROW
				list(, $mContent, $mMod) = $matches;
				// [1] => ....
				// [2] => .(title)[class]{style}<>_

				$elRow = Texy\HtmlElement::el('tr');
				$mod = new Texy\Modifier($mMod);
				$mod->decorate($tx, $elRow);

				$rowClass = $rowCounter % 2 === 0 ? $this->oddClass : $this->evenClass;
				if ($rowClass && !isset($mod->classes[$this->oddClass]) && !isset($mod->classes[$this->evenClass])) {
					$elRow->attrs['class'][] = $rowClass;
				}

				$col = 0;
				$elCell = NULL;

				// special escape sequence \|
				$mContent = str_replace('\\|', "\x13", $mContent);
				$mContent = Texy\Regexp::replace($mContent, '#(\[[^\]]*)\|#', "$1\x13"); // HACK: support for [..|..]

				foreach (explode('|', $mContent) as $cell) {
					$cell = strtr($cell, "\x13", '|');
					// rowSpan
					if (isset($prevRow[$col]) && ($lineMode || ($matches = Texy\Regexp::match($cell, '#\^\ *$|\*??(.*)\ +\^$#AU')))) {
						$prevRow[$col]->rowSpan++;
						if (!$lineMode) {
							$cell = isset($matches[1]) ? $matches[1] : '';
						}
						$prevRow[$col]->text .= "\n" . $cell;
						$col += $prevRow[$col]->colSpan;
						$elCell = NULL;
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
					$matches = Texy\Regexp::match($cell, '#(\*??)\ *'.Texy\Patterns::MODIFIER_HV.'??(.*)'.Texy\Patterns::MODIFIER_HV.'?\ *()$#AU');
					if (!$matches) {
						continue;
					}
					list(, $mHead, $mModCol, $mContent, $mMod) = $matches;
					// [1] => * ^
					// [2] => .(title)[class]{style}<>_
					// [3] => ....
					// [4] => .(title)[class]{style}<>_

					if ($mModCol) {
						$colModifier[$col] = new Texy\Modifier($mModCol);
					}

					if (isset($colModifier[$col])) {
						$mod = clone $colModifier[$col];
					} else {
						$mod = new Texy\Modifier;
					}

					$mod->setProperties($mMod);

					$elCell = new TableCellElement;
					$elCell->setName($isHead || ($mHead === '*') ? 'th' : 'td');
					$mod->decorate($tx, $elCell);
					$elCell->text = $mContent;

					$elRow->add($elCell);
					$prevRow[$col] = $elCell;
					$col++;
				}


				// even up with empty cells
				while ($col < $colCounter) {
					if (isset($prevRow[$col]) && $lineMode) {
						$prevRow[$col]->rowSpan++;
						$prevRow[$col]->text .= "\n";

					} else {
						$elCell = new TableCellElement;
						$elCell->setName($isHead ? 'th' : 'td');
						if (isset($colModifier[$col])) {
							$colModifier[$col]->decorate($tx, $elCell);
						}
						$elRow->add($elCell);
						$prevRow[$col] = $elCell;
					}
					$col++;
				}
				$colCounter = $col;


				if ($elRow->count()) {
					$elPart->add($elRow);
					$rowCounter++;
				} else {
					// redundant row
					foreach ($prevRow as $elCell) {
						$elCell->rowSpan--;
					}
				}

				continue;
			}

			break;
		}

		if ($elPart === NULL) {
			// invalid table
			return FALSE;
		}

		if ($elPart->getName() === 'thead') {
			// thead is optional, tbody is required
			$elPart->setName('tbody');
		}

		$this->finishPart($elPart);


		// event listener
		$tx->invokeHandlers('afterTable', array($parser, $el, $mod));

		return $el;
	}


	/**
	 * Parse text in all cells.
	 * @param  Texy\HtmlElement
	 * @return void
	 */
	private function finishPart($elPart)
	{
		$tx = $this->texy;

		foreach ($elPart->getChildren() as $elRow) {
			foreach ($elRow->getChildren() as $elCell) {
				if ($elCell->colSpan > 1) {
					$elCell->attrs['colspan'] = $elCell->colSpan;
				}

				if ($elCell->rowSpan > 1) {
					$elCell->attrs['rowspan'] = $elCell->rowSpan;
				}

				$text = rtrim($elCell->text);
				if (strpos($text, "\n") !== FALSE) {
					// multiline parse as block
					// HACK: disable tables
					$this->disableTables = TRUE;
					$elCell->parseBlock($tx, Texy\Texy::outdent($text));
					$this->disableTables = FALSE;
				} else {
					$elCell->parseLine($tx, ltrim($text));
				}

				if ($elCell->getText() === '') {
					$elCell->setText("\xC2\xA0"); // &nbsp;
				}
			}
		}
	}

}


/**
 * Table cell TD / TH.
 */
class TableCellElement extends Texy\HtmlElement
{
	/** @var int */
	public $colSpan = 1;

	/** @var int */
	public $rowSpan = 1;

	/** @var string */
	public $text;

}
