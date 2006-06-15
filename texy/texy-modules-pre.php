<?php

/**
 * ------------------------------
 *   TEXY! DEFAULT PRE- MODULES
 * ------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Modules for parsing parts of text 
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
 * NOTEXY! module
 * --------------
 *
 *  <<< .... >>> 
 *
 */
class TexyNoTexyModule extends TexyModule {


  function init() {
    $this->registerInlinePattern('hashToElement',   '#'.TEXY_PATTERN_HASH.'#');
  }


  function preProcess() {
    $this->texy->text = preg_replace_callback('#<<<(.*)>>>#Uis', array(&$this, 'replace'), $this->texy->text);
  }


  function replace(&$matches) {
    list($match, $mContent) = $matches;
    //    [1] => ...
    
    return $this->texy->freezer->add($mContent);
  }


  function hashToElement(&$matches) {
    $el = &new TexyHashElement($this->texy);
    $el->content = $this->texy->freezer->table[$matches[0]];
    return array(&$el);
  }


} // TexyNoTexyModule










?>