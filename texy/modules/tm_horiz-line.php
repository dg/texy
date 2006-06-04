<?php

/**
 * ------------------------------------------
 *   HORIZONTAL LINE - TEXY! DEFAULT MODULE
 * ------------------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
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
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizlineModule extends TexyModule {
  var $allowed       = true;                  // generally disable / enable


  /***
   * Module initialization.
   */
  function init() {
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
  function &processBlock(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ---
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >

    $el = &new TexyHorizLineElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    return $el;
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