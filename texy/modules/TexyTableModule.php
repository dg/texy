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
 * Table module
 */
class TexyTableModule extends TexyModule
{
    protected $allow = array('table');

    /** @var string  CSS class for odd rows */

    public $oddClass;
    /** @var string  CSS class for even rows */
    public $evenClass;

    private $isHead;
    private $colModifier;
    private $last;
    private $row;



    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^(?:'.TEXY_MODIFIER_HV.'\n)?'   // .{color: red}
          . '\|.*()$#mU',                     // | ....
            'table'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *  .(title)[class]{style}>
     *  |------------------
     *  | xxx | xxx | xxx | .(..){..}[..]
     *  |------------------
     *  | aa  | bb  | cc  |
     */
    public function processBlock($parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => >
        //    [5] => _

        $tx = $this->texy;
        $el = new TexyBlockElement($tx);

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        $el->tags[0] = $mod->generate($tx, 'table');

//        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);

        $parser->moveBackward();

        if ($parser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_MODIFIER_H.'?()$#Um', $matches)) {
            list(, , $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
            //    [1] => # / =
            //    [2] => ....
            //    [3] => (title)
            //    [4] => [class]
            //    [5] => {style}
            //    [6] => >

            $caption = new TexyTextualElement($tx);
            $mod = new TexyModifier;
            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $caption->tags[0] = $mod->generate($tx, 'caption');
            $caption->parse($mContent);
            $el->children[] = $caption;
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
                $el->children[] = $elRow;
                $this->row++;
                continue;
            }

            break;
        }

        $parser->children[] = $el;
    }



    protected function processRow($parser)
    {
        $tx = $this->texy;

        if (!$parser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U', $matches)) {
            return FALSE;
        }
        list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
        //    [1] => ....
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => _

        $elRow = new TexyBlockElement($tx);

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        $elRow->tags[0] = $mod->generate($tx, 'tr');

        if ($this->row % 2 === 0) {
            if ($this->oddClass) $elRow->tags[0]->class[] = $this->oddClass;
        } else {
            if ($this->evenClass) $elRow->tags[0]->class[] = $this->evenClass;
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
            if ($field === '^') { // rowspan
                if (isset($this->last[$col])) {
                    $this->last[$col]->rowSpan++;
                    $col += $this->last[$col]->colSpan;
                    continue;
                }
            }

            if (!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?()$#AU', $field, $matches)) continue;
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
                $this->colModifier[$col] = new TexyModifier;
                $this->colModifier[$col]->setProperties($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
            }

            $elField = new TexyTableFieldElement($tx);

            if (isset($this->colModifier[$col]))
                $mod = clone $this->colModifier[$col];
            else
                $mod = new TexyModifier;

            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);

            $elField->tags[0] = $mod->generate($tx,  ($this->isHead || ($mHead === '*')) ? 'th' : 'td' );

            $elField->parse($mContent);
            if ($elField->content == '') $elField->content = "\xC2\xA0"; // &nbsp;

            $elRow->children[] = $elField;
            $this->last[$col] = $elField;
            $col++;
        }

        return $elRow;
    }

} // TexyTableModule




/**
 * Table field TD / TH
 */
class TexyTableFieldElement extends TexyTextualElement
{
    public $colSpan = 1;
    public $rowSpan = 1;


    public function toHtml()
    {
        if ($this->colSpan <> 1) $this->tags[0]->colspan = $this->colSpan;
        if ($this->rowSpan <> 1) $this->tags[0]->rowspan = $this->rowSpan;
        return parent::toHtml();
    }

} // TexyTableFieldElement
