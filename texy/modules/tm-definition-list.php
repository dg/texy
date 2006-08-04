<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();
require_once TEXY_DIR.'modules/tm-list.php';




/**
 * DEFINITION LIST MODULE CLASS
 */
class TexyDefinitionListModule extends TexyListModule {
    var $allowed = array(
           '*'            => true,
           '-'            => true,
           '+'            => true,
    );

    // private
    var $translate = array(    //  rexexp  class
           '*'            => array('\*',   ''),
           '-'            => array('\-',   ''),
           '+'            => array('\+',   ''),
    );



    /**
     * Module initialization.
     */
    function init()
    {
        $bullets = array();
        foreach ($this->allowed as $bullet => $allowed)
            if ($allowed) $bullets[] = $this->translate[$bullet][0];

        $this->registerBlockPattern('processBlock', '#^(?:<MODIFIER_H>\n)?'                              // .{color:red}
                                                                                            . '(\S.*)\:\ *<MODIFIER_H>?\n'                         // Term:
                                                                                            . '(\ +)('.implode('|', $bullets).')\ +\S.*$#mU');   //    - description
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
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mMod1, $mMod2, $mMod3, $mMod4,
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

        $texy = & $this->texy;
        $el = &new TexyListElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->tag = 'dl';

        $bullet = '';
        foreach ($this->translate as $type)
            if (preg_match('#'.$type[0].'#A', $mBullet)) {
                $bullet = $type[0];
                $el->modifier->classes[] = $type[1];
                break;
            }

        $blockParser->element->appendChild($el);

        $blockParser->moveBackward(2);

        $patternTerm = $texy->translatePattern('#^\n?(\S.*)\:\ *<MODIFIER_H>?()$#mUA');
        $bullet = preg_quote($mBullet);

        while (true) {
            if ($elItem = &$this->processItem($blockParser, preg_quote($mBullet), true)) {
                $elItem->tag = 'dd';
                $el->children[] = & $elItem;
                continue;
            }

            if ($blockParser->receiveNext($patternTerm, $matches)) {
                list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
                //    [1] => ...
                //    [2] => (title)
                //    [3] => [class]
                //    [4] => {style}
                //    [5] => >
                $elItem = &new TexyTextualElement($texy);
                $elItem->tag = 'dt';
                $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                $elItem->parse($mContent);
                $el->children[] = & $elItem;
                continue;
            }

            break;
        }
    }

} // TexyDefinitionListModule








?>