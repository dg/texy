<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
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
        $this->texy->registerBlockPattern($this, 'processBlock', '#^(\- |\-|\* |\*){3,}\ *<MODIFIER_H>?()$#mU');
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
    function processBlock(&$parser, $matches)
    {
        list(, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ---
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

        $el = &new TexyHorizLineElement($this->texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $parser->element->appendChild($el);
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