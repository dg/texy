<?php

/**
 * ------------------------------------------
 *   HORIZONTAL LINE - TEXY! DEFAULT MODULE
 * ------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizLineModule extends TexyModule {


    /***
     * Module initialization.
     */
    function init()
    {
        $this->registerBlockPattern('processBlock', '#^(\- |\-|\* |\*){3,}\ *MODIFIER_H?()$#mU');
    }



    /***
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
        $blockParser->addChildren($el);
    }




} // TexyHorizlineModule






/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT HORIZONTAL LINE
 */
class TexyHorizLineElement extends TexyBlockElement {
    var $tag = 'hr';


} // TexyHorizLineElement




?>