<?php

/**
 * ------------------------------
 *   TEXY! MODULES BASE CLASSES
 * ------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
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
  var $texy;
  var $key;


 
  function __constructor(&$texy) {
    $this->texy = & $texy;
  }


  
  // PHP 4 compatible constructor - can be removed in PHP5
  function TexyModule(&$texy) {      
    $args = & func_get_args();
    $args[0] = &$texy;          // just a little trick, but works :-)
    call_user_func_array(array(& $this, '__constructor'), $args);
  }



  function init() {
  }


  // pre-process $texy->text
  function preProcess() {
  }



  // post-process $texy->DOM, $texy->elements
  function postProcess() {
  }




  function adjustPattern($pattern) {
    return strtr($pattern, 
                     array('MODIFIER_HV' => TEXY_PATTERN_MODIFIER_HV,
                           'MODIFIER_H'  => TEXY_PATTERN_MODIFIER_H,
                           'MODIFIER'    => TEXY_PATTERN_MODIFIER,
                           'LINK'        => TEXY_PATTERN_LINK,
                     ));
  }


  function registerInlinePattern($func, $pattern, $user_args = null) {
    $this->texy->patternsInline[] = array(
             'replacement' => array(&$this, $func), 
             'pattern'     => $this->adjustPattern($pattern) ,
             'user'        => $user_args
    );
  }


  function registerBlockPattern($func, $pattern, $user_args = null) {
    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Not a block pattern. Class '.get_class($this).', pattern '.htmlSpecialChars($pattern));
                
    $this->texy->patternsBlock[] = array(
             'func'    => array(&$this, $func), 
             'pattern' => $this->adjustPattern($pattern)  . 'm',  // multiline!
             'user'    => $user_args
    );
  }




} // TexyModule






?>