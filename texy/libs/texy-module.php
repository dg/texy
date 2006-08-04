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
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule {
    var $texy;             // parent Texy! object (reference to itself is: $texy->modules->__CLASSNAME__)
    var $allowed = TEXY_ALL;   // module configuration


    // PHP5 constructor
    function __construct(&$texy)
    {
        $this->texy = & $texy;
    }


    // PHP4 constructor
    function TexyModule(&$texy)
    {
        // call php5 constructor
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



    function registerLinePattern($func, $pattern, $user_args = null)
    {
        $this->texy->patternsLine[] = array(
                         'handler'     => array(&$this, $func),
                         'pattern'     => $this->texy->translatePattern($pattern) ,
                         'user'        => $user_args
        );
    }


    function registerBlockPattern($func, $pattern, $user_args = null)
    {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Class '.get_class($this).', pattern '.htmlSpecialChars($pattern));

        $this->texy->patternsBlock[] = array(
                         'handler'     => array(&$this, $func),
                         'pattern'     => $this->texy->translatePattern($pattern)  . 'm',  // force multiline!
                         'user'        => $user_args
        );
    }








} // TexyModule





?>