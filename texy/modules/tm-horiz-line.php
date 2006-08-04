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






/**
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizLineModule extends TexyModule {


    /**
     * Module initialization.
     */
    function init()
    {
        $this->registerBlockPattern('processBlock', '#^(\- |\-|\* |\*){3,}\ *<MODIFIER_H>?()$#mU');
    }



    /**
     * Callback function (for blocks)
     *
     *            ---------------------------
     *
     *            - - - - - - - - - - - - - -
     *
     *            ***************************
     *
     *            * * * * * * * * * * * * * *
     *
     */
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ---
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

        $el = &new TexyHorizLineElement($this->texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $blockParser->element->appendChild($el);
    }




} // TexyHorizlineModule






/***************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT HORIZONTAL LINE
 */
class TexyHorizLineElement extends TexyBlockElement {
    var $tag = 'hr';


} // TexyHorizLineElement




?>