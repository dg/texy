<?php

/**
 * -----------------------------
 *   TEXY! ELEMENTS BASE CLASS
 * -----------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Elements of Texy! "DOM"
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
 * Texy! ELEMENT BASE CLASS
 * ------------------------
 */
class TexyDOMElement {
  var $texy;
  var $children = array();

  

  function __constructor(&$texy) {
    if (!$texy) die('No $texy in constructor of '.get_class($this));  
    $this->texy = & $texy;
    $texy->elements[] = &$this;
  }


  
  // PHP 4 compatible constructor - can be removed in PHP5
  function TexyDOMElement(&$texy) {
    $args = & func_get_args();
    $args[0] = &$texy;          // a little trick to transfer reference :-)
    call_user_func_array(array(& $this, '__constructor'), $args);
  }




  // $this->children to string
  function childrenToHTML() {
    // merge array
    $res = '';
    foreach (array_keys($this->children) as $key)
      if (is_object($this->children[$key]))         // node is TexyDOMElement
        $res .= $this->children[$key]->toHTML();
      else 
        $res .= Texy::htmlChars($this->children[$key]);  // node is string
        
    return $res;    
  }



  // convert element to HTML string
  function toHTML() {
    return $this->childrenToHTML();
  }




  function hasTextualContent() {
    foreach (array_keys($this->children) as $key) {
      if (is_object($this->children[$key]))         // node is TexyDOMElement
        $has = $this->children[$key]->hasTextualContent();
      else 
        $has = strlen($this->children[$key]) > 0;  // node is string
        
      if ($has) return true;    
    }
    
    return false;
  }

}  // TexyDOMElement







?>