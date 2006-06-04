<?php

/**
 * -----------------------------
 *   TEXY! MODULES BASE CLASSE
 * -----------------------------
 *
 * Version 0.9 beta
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
  var $texy;  // parent Texy! object (reference to itself is: $texy->modules[__CLASSNAME__])




  function TexyModule(&$texy) {
    $this->texy = & $texy;
  }



  // register all line & block patterns a routines
  function init() {
  }


  // block's pre-process
  function preProcess(&$text) {
  }



  // block's post-process
  function postProcess(&$text) {
  }



  // single line post-process
  function inlinePostProcess(&$line) {
  }



  function adjustPattern($pattern) {
    return strtr($pattern,
                     array('MODIFIER_HV' => TEXY_PATTERN_MODIFIER_HV,
                           'MODIFIER_H'  => TEXY_PATTERN_MODIFIER_H,
                           'MODIFIER'    => TEXY_PATTERN_MODIFIER,
                     ));
  }


  function registerLinePattern($func, $pattern, $user_args = null) {
    $this->texy->patternsLine[] = array(
             'replacement' => array(&$this, $func),
             'pattern'     => $this->adjustPattern($pattern) ,
             'user'        => $user_args
    );
  }


  function registerBlockPattern($func, $pattern, $user_args = null) {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Class '.get_class($this).', pattern '.htmlSpecialChars($pattern));

    $this->texy->patternsBlock[] = array(
             'func'    => array(&$this, $func),
             'pattern' => $this->adjustPattern($pattern)  . 'm',  // multiline!
             'user'    => $user_args
    );
  }




} // TexyModule





?>
