<?php

/**
 * -----------------------------
 *   TEXY! MODULES BASE CLASSE
 * -----------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Texy! modules base class
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
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule {
    var $texy;             // parent Texy! object (reference to itself is: $texy->modules->__CLASSNAME__)
    var $allowed = TEXY_ALL;   // module configuration


    function TexyModule(&$texy)
    {
        $this->texy = & $texy;
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


/* not used yet
    // single line pre-process
    function linePreProcess(&$line)
    {
    }
*/

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