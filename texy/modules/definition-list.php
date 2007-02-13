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
 * DEFINITION LIST MODULE CLASS
 */
class TexyDefinitionListModule extends TexyListModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $allowed = array(
        '*'            => TRUE,
        '-'            => TRUE,
        '+'            => TRUE,
    );

    // private
    public $translate = array(    //  rexexp  class
        '*'            => array('\*',   ''),
        '-'            => array('\-',   ''),
        '+'            => array('\+',   ''),
    );



    /**
     * Module initialization.
     */
    public function init()
    {
        $bullets = array();
        foreach ($this->allowed as $bullet => $allowed)
            if ($allowed) $bullets[] = $this->translate[$bullet][0];

        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:<MODIFIER_H>\n)?'                          // .{color:red}
          . '(\S.*)\:\ *<MODIFIER_H>?\n'                     // Term:
          . '(\ +)('.implode('|', $bullets).')\ +\S.*$#mU'   //    - description
        );
    }



    /**
     * Callback function (for blocks)
     *
     *            Term: .(title)[class]{style}>
     *              - description 1
     *              - description 2
     *              - description 3
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4,
                                 $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4,
                                 $mSpaces, $mBullet) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => >

        //    [5] => ...
        //    [6] => (title)
        //    [7] => [class]
        //    [8] => {style}
        //    [9] => >

        //   [10] => space
        //   [11] => - * +

        $texy =  $this->texy;
        $el = new TexyBlockElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->tag = 'dl';

        $bullet = '';
        foreach ($this->translate as $type)
            if (preg_match('#'.$type[0].'#A', $mBullet)) {
                $bullet = $type[0];
                $el->modifier->classes[] = $type[1];
                break;
            }

        $parser->moveBackward(2);

        $patternTerm = $texy->translatePattern('#^\n?(\S.*)\:\ *<MODIFIER_H>?()$#mUA');
        $bullet = preg_quote($mBullet);

        while (TRUE) {
            if ($elItem = $this->processItem($parser, preg_quote($mBullet), TRUE)) {
                $elItem->tag = 'dd';
                $el->appendChild($elItem);
                continue;
            }

            if ($parser->receiveNext($patternTerm, $matches)) {
                list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
                //    [1] => ...
                //    [2] => (title)
                //    [3] => [class]
                //    [4] => {style}
                //    [5] => >
                $elItem = new TexyTextualElement($texy);
                $elItem->tag = 'dt';
                $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                $elItem->parse($mContent);
                $el->appendChild($elItem);
                continue;
            }

            break;
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);
    }

} // TexyDefinitionListModule
