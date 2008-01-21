<?php

/**
 * Texy! - web text markup-language
 * --------------------------------
 *
 * Copyright (c) 2004, 2008 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * Table module
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @package    Texy
 * @version    $Revision$ $Date$
 */
final class TexyTableModule extends TexyModule
{
    /** @var string  CSS class for odd rows */
    public $oddClass;

    /** @var string  CSS class for even rows */
    public $evenClass;



    public function __construct($texy)
    {
        $this->texy = $texy;

        $texy->registerBlockPattern(
            array($this, 'patternTable'),
            '#^(?:'.TEXY_MODIFIER_HV.'\n)?'   // .{color: red}
          . '\|.*()$#mU',                     // | ....
            'table'
        );
    }



    /**
     * Callback for:
     *
     *  .(title)[class]{style}>
     *  |------------------
     *  | xxx | xxx | xxx | .(..){..}[..]
     *  |------------------
     *  | aa  | bb  | cc  |
     *
     * @param  TexyBlockParser
     * @param  array      regexp matches
     * @param  string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternTable($parser, $matches)
    {
        list(, $mMod) = $matches;
        //    [1] => .(title)[class]{style}<>_

        $tx = $this->texy;

        $el = TexyHtml::el('table');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);

        $parser->moveBackward();

        if ($parser->next('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_MODIFIER_H.'?()$#Um', $matches)) {
            list(, , $mContent, $mMod) = $matches;
            //    [1] => # / =
            //    [2] => ....
            //    [3] => .(title)[class]{style}<>

            $caption = $el->create('caption');
            $mod = new TexyModifier($mMod);
            $mod->decorate($tx, $caption);
            $caption->parseLine($tx, $mContent);
        }

        $isHead = FALSE;
        $colModifier = array();
        $prevRow = array(); // rowSpan building helper
        $rowCounter = 0;
        $colCounter = 0;
        $elPart = NULL;

        while (TRUE) {
            if ($parser->next('#^\|[+-]{3,}$#Um', $matches)) {
                $isHead = !$isHead;
                $prevRow = array();
                continue;
            }

            if ($parser->next('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U', $matches)) {
                // smarter head detection
                if ($rowCounter === 0 && !$isHead && $parser->next('#^\|[+-]{3,}$#Um', $foo)) {
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
                //    [1] => ....
                //    [2] => .(title)[class]{style}<>_

                $elRow = TexyHtml::el('tr');
                $mod = new TexyModifier($mMod);
                $mod->decorate($tx, $elRow);

                $rowClass = $rowCounter % 2 === 0 ? $this->oddClass : $this->evenClass;
                if ($rowClass && !isset($mod->classes[$this->oddClass]) && !isset($mod->classes[$this->evenClass])) {
                    $elRow->attrs['class'][] = $rowClass;
                }

                $col = 0;
                $elCell = NULL;

                // special escape sequence \|
                $mContent = str_replace('\\|', '&#x7C;', $mContent);

                foreach (explode('|', $mContent) as $cell) {
                    // colSpan
                    if ($cell === '' && $elCell) {
                        $elCell->colSpan++;
                        unset($prevRow[$col]);
                        $col++;
                        continue;
                    }

                    // rowSpan
                    if (isset($prevRow[$col]) && preg_match('#\^\ *$|\*??(.*)\ +\^$#AU', $cell, $matches)) {
                        $prevRow[$col]->rowSpan++;
                        $matches[] = '';
                        $prevRow[$col]->text .= "\n" . $matches[1];
                        $col += $prevRow[$col]->colSpan;
                        $elCell = NULL;
                        continue;
                    }

                    // common cell
                    if (!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?\ *()$#AU', $cell, $matches)) continue;
                    list(, $mHead, $mModCol, $mContent, $mMod) = $matches;
                    //    [1] => * ^
                    //    [2] => .(title)[class]{style}<>_
                    //    [3] => ....
                    //    [4] => .(title)[class]{style}<>_

                    if ($mModCol) {
                        $colModifier[$col] = new TexyModifier($mModCol);
                    }

                    if (isset($colModifier[$col]))
                        $mod = clone $colModifier[$col];
                    else
                        $mod = new TexyModifier;

                    $mod->setProperties($mMod);

                    $elCell = new TexyTableCellElement;
                    $elCell->setName($isHead || ($mHead === '*') ? 'th' : 'td');
                    $mod->decorate($tx, $elCell);
                    $elCell->text = $mContent;

                    $elRow->add($elCell);
                    $prevRow[$col] = $elCell;
                    $col++;
                }


                // even up with empty cells
                while ($col < $colCounter) {
                    $elCell = new TexyTableCellElement;
                    $elCell->setName($isHead ? 'th' : 'td');
                    if (isset($colModifier[$col])) {
                        $colModifier[$col]->decorate($tx, $elCell);
                    }
                    $elRow->add($elCell);
                    $prevRow[$col] = $elCell;
                    $col++;
                }
                $colCounter = $col;


                if ($elRow->count()) {
                    $elPart->add($elRow);
                    $rowCounter++;
                } else {
                    // redundant row
                    foreach ($prevRow as $elCell) $elCell->rowSpan--;
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
     * Parse text in all cells
     * @param  TexyHtml
     * @return void
     */
    private function finishPart($elPart)
    {
        $tx = $this->texy;

        foreach ($elPart->getChildren() as $elRow)
        {
            foreach ($elRow->getChildren() as $elCell)
            {
                if ($elCell->colSpan > 1) $elCell->attrs['colspan'] = $elCell->colSpan;

                if ($elCell->rowSpan > 1) {
                    $elCell->attrs['rowspan'] = $elCell->rowSpan;
                    $text = Texy::outdent($elCell->text);
                    if (strpos($text, "\n") !== FALSE) {
                        // multiline parse as block
                        $elCell->parseBlock($tx, $text);
                        continue;
                    }
                }

                $elCell->parseLine($tx, trim($elCell->text));
                if ($elCell->getText() === '') $elCell->setText("\xC2\xA0"); // &nbsp;
            }
        }
    }

}




/**
 * Table cell TD / TH
 * @package Texy
 */
class TexyTableCellElement extends TexyHtml
{
    /** @var int */
    public $colSpan = 1;

    /** @var int */
    public $rowSpan = 1;

    /** @var string */
    public $text;

}
