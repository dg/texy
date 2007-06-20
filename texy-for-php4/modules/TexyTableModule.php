<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy')) die();



/**
 * Table module
 */
class TexyTableModule extends TexyModule
{
    var $syntax = array('table' => TRUE); /* protected */

    /** @var string  CSS class for odd rows */

    var $oddClass;
    /** @var string  CSS class for even rows */
    var $evenClass;

    var $isHead; /* private */
    var $colModifier; /* private */
    var $last; /* private */
    var $row; /* private */



    function begin()
    {
        $this->texy->registerBlockPattern(
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
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    function patternTable($parser, $matches)
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

            $caption = $el->add('caption');
            $mod = new TexyModifier($mMod);
            $mod->decorate($tx, $caption);
            $caption->parseLine($tx, $mContent);
        }

        $this->isHead = FALSE;
        $this->colModifier = array();
        $this->last = array();
        $this->row = 0;

        while (TRUE) {
            if ($parser->next('#^\|[+-]{3,}$#Um', $matches)) {
                $this->isHead = !$this->isHead;
                continue;
            }

            if ($elRow = $this->patternRow($parser)) {
                $el->addChild($elRow);
                $this->row++;
                continue;
            }

            break;
        }

        // event listener
        if (is_callable(array($tx->handler, 'afterTable')))
            $tx->handler->afterTable($parser, $el, $mod);

        return $el;
    }



    /**
     * Handles single row: | xxx | xxx | xxx | .(..){..}[..]
     * @param TexyBlockParser
     * @return TexyHtml|string|FALSE
     */
    function patternRow($parser) /* protected */
    {
        $tx = $this->texy;

        $matches = NULL;
        if (!$parser->next('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U', $matches))
            return FALSE;

        list(, $mContent, $mMod) = $matches;
        //    [1] => ....
        //    [2] => .(title)[class]{style}<>_

        $elRow = TexyHtml::el('tr');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $elRow);

        if ($this->row % 2 === 0) {
            if ($this->oddClass) $elRow->attrs['class'][] = $this->oddClass;
        } else {
            if ($this->evenClass) $elRow->attrs['class'][] = $this->evenClass;
        }

        $col = 0;
        $elField = NULL;

        // special escape sequence \|
        $mContent = str_replace('\\|', '&#x7C;', $mContent);

        foreach (explode('|', $mContent) as $field) {
            if (($field == '') && $elField) { // colspan
                $elField->colspan++;
                unset($this->last[$col]);
                $col++;
                continue;
            }

            $field = rtrim($field);
            if ($field === '^') { // rowspan
                if (isset($this->last[$col])) {
                    $this->last[$col]->rowspan++;
                    $col += $this->last[$col]->colspan;
                    continue;
                }
            }

            if (!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?()$#AU', $field, $matches)) continue;
            list(, $mHead, $mModCol, $mContent, $mMod) = $matches;
            //    [1] => * ^
            //    [2] => .(title)[class]{style}<>_
            //    [3] => ....
            //    [4] => .(title)[class]{style}<>_

            if ($mModCol) {
                $this->colModifier[$col] = new TexyModifier($mModCol);
            }

            if (isset($this->colModifier[$col]))
                $mod = clone ($this->colModifier[$col]);
            else
                $mod = new TexyModifier;

            $mod->setProperties($mMod);

            $elField = new TexyTableFieldElement;
            $elField->setName($this->isHead || ($mHead === '*') ? 'th' : 'td');
            $mod->decorate($tx, $elField);

            $elField->parseLine($tx, $mContent);
            if ($elField->children === '') $elField->children  = "\xC2\xA0"; // &nbsp;

            $elRow->addChild($elField);
            $this->last[$col] = $elField;
            $col++;
        }

        return $elRow;
    }

}




/**
 * Table field TD / TH
 */
class TexyTableFieldElement extends TexyHtml
{
    var $colspan = 1;
    var $rowspan = 1;


    function startTag()
    {
        $this->attrs['colspan'] = $this->colspan < 2 ? NULL : $this->colspan;
        $this->attrs['rowspan'] = $this->rowspan < 2 ? NULL : $this->rowspan;
        return parent::startTag();
    }

}
