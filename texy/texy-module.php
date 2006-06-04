<?php

/**
 * -----------------------------
 *   TEXY! MODULES BASE CLASSE
 * -----------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Texy! modules are used to parse text into elements (DOM) by regular expressions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule {
  var $texy;             // parent Texy! object (reference to itself is: $texy->modules->__CLASSNAME__)
  var $allowed = true;   // module configuration


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
             'replacement' => array(&$this, $func),
             'pattern'     => $this->texy->translatePattern($pattern) ,
             'user'        => $user_args
    );
  }


  function registerBlockPattern($func, $pattern, $user_args = null)
  {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Class '.get_class($this).', pattern '.htmlSpecialChars($pattern));

    $this->texy->patternsBlock[] = array(
             'func'    => array(&$this, $func),
             'pattern' => $this->texy->translatePattern($pattern)  . 'm',  // force multiline!
             'user'    => $user_args
    );
  }








} // TexyModule





?>