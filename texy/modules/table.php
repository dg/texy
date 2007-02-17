<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * TABLE MODULE CLASS
 */
class TexyTableModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $oddClass     = '';
    public $evenClass    = '';

    private $isHead;
    private $colModifier;
    private $last;
    private $row;



    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:<MODIFIER_HV>\n)?'      // .{color: red}
          . '\|.*()$#mU'                  // | ....
        );
    }





    /**
     * Callback function (for blocks)
     *
     *            .(title)[class]{style}>
     *            |------------------
     *            | xxx | xxx | xxx | .(..){..}[..]
     *            |------------------
     *            | aa  | bb  | cc  |
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => >
        //    [5] => _

        $texy =  $this->texy;
        $el = new TexyTableElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);

        $parser->moveBackward();

        if ($parser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_PATTERN_MODIFIER_H.'?()$#Um', $matches)) {
            list(, , $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
            //    [1] => # / =
            //    [2] => ....
            //    [3] => (title)
            //    [4] => [class]
            //    [5] => {style}
            //    [6] => >

            $el->caption = new TexyTextualElement($texy);
            $el->caption->tag = 'caption';
            $el->caption->parse($mContent);
            $el->caption->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        }

        $this->isHead = FALSE;
        $this->colModifier = array();
        $this->last = array();
        $this->row = 0;

        while (TRUE) {
            if ($parser->receiveNext('#^\|\-{3,}$#Um', $matches)) {
                $this->isHead = !$this->isHead;
                continue;
            }

            if ($elRow = $this->processRow($parser)) {
                if ($this->handler)
                    if (call_user_func_array($this->handler, array($elRow, 'row')) === FALSE) continue;

                $el->appendChild($elRow);
                $this->row++;
                continue;
            }

            break;
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el, 'table')) === FALSE) return;

        $parser->element->appendChild($el);
    }






    protected function processRow($parser) {
        $texy =  $this->texy;

        if (!$parser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#U', $matches)) {
            return FALSE;
        }
        list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
        //    [1] => ....
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => _

        $elRow = new TexyBlockElement($this->texy);
        $elRow->tag = 'tr';
        $elRow->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        if ($this->row % 2 == 0) {
            if ($this->oddClass) $elRow->modifier->classes[] = $this->oddClass;
        } else {
            if ($this->evenClass) $elRow->modifier->classes[] = $this->evenClass;
        }

        $col = 0;
        $elField = NULL;
        foreach (explode('|', $mContent) as $field) {
            if (($field == '') && $elField) { // colspan
                $elField->colSpan++;
                unset($this->last[$col]);
                $col++;
                continue;
            }

            $field = rtrim($field);
            if ($field == '^') { // rowspan
                if (isset($this->last[$col])) {
                    $this->last[$col]->rowSpan++;
                    $col += $this->last[$col]->colSpan;
                    continue;
                }
            }

            if (!preg_match('#(\*??)\ *'.TEXY_PATTERN_MODIFIER_HV.'??(.*)'.TEXY_PATTERN_MODIFIER_HV.'?()$#AU', $field, $matches)) continue;
            list(, $mHead, $mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
            //    [1] => * ^
            //    [2] => (title)
            //    [3] => [class]
            //    [4] => {style}
            //    [5] => <
            //    [6] => ^
            //    [7] => ....
            //    [8] => (title)
            //    [9] => [class]
            //    [10] => {style}
            //    [11] => <>
            //    [12] => ^

            if ($mModCol1 || $mModCol2 || $mModCol3 || $mModCol4 || $mModCol5) {
                $this->colModifier[$col] = new TexyModifier($this->texy);
                $this->colModifier[$col]->setProperties($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
            }

            $elField = new TexyTableFieldElement($texy);
            $elField->tag = ($this->isHead || ($mHead == '*')) ? 'th' : 'td';
            if (isset($this->colModifier[$col]))
                $elField->modifier->copyFrom($this->colModifier[$col]);
            $elField->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
            $elField->parse($mContent);
            $elRow->appendChild($elField);
            $this->last[$col] = $elField;
            $col++;
        }

        return $elRow;
    }


} // TexyTableModule










/**
 * HTML ELEMENT TABLE
 */
class TexyTableElement extends TexyBlockElement
{
    public $tag = 'table';
    public $caption;


    protected function generateContent()
    {
        $html = parent::generateContent();

        if ($this->caption)
            $html = $this->caption->__toString() . $html;

        return $html;
    }


} // TexyTableElement







/**
 * HTML ELEMENT TD / TH
 */
class TexyTableFieldElement extends TexyTextualElement
{
    public $colSpan = 1;
    public $rowSpan = 1;

    protected function generateTags(&$tags)
    {
        parent::generateTags($tags);
        if ($this->colSpan <> 1) $tags[$this->tag]['colspan'] = (int) $this->colSpan;
        if ($this->rowSpan <> 1) $tags[$this->tag]['rowspan'] = (int) $this->rowSpan;
    }


    protected function generateContent()
    {
        $html = parent::generateContent();
        return $html == '' ? '&#160;' : $html;
    }

} // TexyTableFieldElement
