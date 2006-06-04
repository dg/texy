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
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule {
    var $texy;             // parent Texy! object
    var $allowed = TEXY_ALL;   // module configuration


    function __construct(&$texy)
    {
        $this->texy = & $texy;
        $texy->registerModule($this);
    }


    /**
     * PHP4 compatible constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyModule(&$texy)
    {
        // generate references
        if (PHP_VERSION < 5) foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$texy));
    }



    // register all line & block patterns a routines
    function init()
    {
    }


    // block's pre-process
    function preProcess(&$text)
    {
    }



    // block's post-process
    function postProcess(&$text)
    {
    }


    // single line post-process
    function linePostProcess(&$line)
    {
    }




} // TexyModule





?>