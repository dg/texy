<?php

/**
 * --------------------------
 *   TEXY! DEFAULT ELEMENTS
 * --------------------------
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
 * Texy! DOM 
 * ---------
 */
class TexyDOM extends TexyDOMElement {

}  // TexyDOM



/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 * ---------------------------------
 */
class TexyScriptElement extends TexyDOMElement {

  function toHTML() {
    return '<--internal use-->';
  }

}  // TexyScriptElement





/**
 * Texy! HASH CODE
 * ---------------
 */
class TexyHashElement extends TexyDOMElement {
  var $content;
  
  function toHTML() {
    return $this->texy->freezer->add($this->content);
  }

}  // TexyHashElement







/**
 * ELEMENT WHICH REPRESENTS "USER HTML TAG"
 * ----------------------------------------
 */
class TexySimpleTagElement extends TexyDOMElement {
  var $closing;
  var $empty;
  var $tag;
  var $attr;

  

  function __constructor(&$texy, $tag = null) {
    parent::__constructor($texy);
    if ($tag) $this->tag = $tag;
  }



  function toHTML() { 
    if (!$this->tag) return '';
    
    if ($this->closing)
      return TexyHTMLElement::closingTag($this->tag);
    else
      return TexyHTMLElement::openingTag($this->tag, $this->attr, $this->empty);
  }


} // TexySimpleTagElement





?>