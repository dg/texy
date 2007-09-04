<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



/**
 * Table module
 */
final class TexyTableModule extends TexyModule
{
    /** @var string  CSS class for odd rows */
    public $oddClass;

    /** @var string  CSS class for even rows */
    public $evenClass;

    /** @var bool */
    private $isHead;

    /** @var array */
    private $colModifier;

    /** @var array */
    private $last;

    /** @var int */
    private $rowCounter;



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
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
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

            $caption = $el->add('caption');
            $mod = new TexyModifier($mMod);
            $mod->decorate($tx, $caption);
            $caption->parseLine($tx, $mContent);
        }

        $this->isHead = FALSE;
        $this->colModifier = array();
        $this->last = array();
        $this->rowCounter = 0;
        $elPart = NULL;

        while (TRUE) {
            if ($parser->next('#^\|[+-]{3,}$#Um', $matches)) {
                $this->isHead = !$this->isHead;
                continue;
            }

            if ($parser->next('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U', $matches)) {
                // smarter head detection
                if ($this->rowCounter === 0 && !$this->isHead && $parser->next('#^\|[+-]{3,}$#Um', $foo)) {
                    $this->isHead = TRUE;
                    $parser->moveBackward();
                }

                if ($elPart === NULL) {
                    $elPart = $el->add($this->isHead ? 'thead' : 'tbody');

                } elseif (!$this->isHead && $elPart->getName() === 'thead') {
                    $elPart = $el->add('tbody');
                }

                $elPart->addChild($this->patternRow($matches));
                $this->rowCounter++;
                continue;
            }

            break;
        }

        // event listener
        $tx->invokeHandlers('afterTable', array($parser, $el, $mod));

        return $el;
    }



    /**
     * Handles single row: | xxx | xxx | xxx | .(..){..}[..]
     * @param array
     * @return TexyHtml
     */
    private function patternRow($matches)
    {
        $tx = $this->texy;
        list(, $mContent, $mMod) = $matches;
        //    [1] => ....
        //    [2] => .(title)[class]{style}<>_

        $elRow = TexyHtml::el('tr');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $elRow);

        if ($this->rowCounter % 2 === 0) {
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
                $mod = clone $this->colModifier[$col];
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
    /** @var int */
    public $colspan = 1;

    /** @var int */
    public $rowspan = 1;


    public function startTag()
    {
        $this->attrs['colspan'] = $this->colspan < 2 ? NULL : $this->colspan;
        $this->attrs['rowspan'] = $this->rowspan < 2 ? NULL : $this->rowspan;
        return parent::startTag();
    }

}
