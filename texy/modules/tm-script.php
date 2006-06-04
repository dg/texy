<?php

/**
 * -----------------------------------
 *   SCRIPTS - TEXY! DEFAULT MODULES
 * -----------------------------------
 *
 * Version 1 Release Candidate
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
 * SCRIPTS MODULE CLASS
 */
class TexyScriptModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerLinePattern('processLine', '#\$\{([^\}'.TEXY_HASH.'])+\}()#U');
  }



  /***
   * Callback function: ${...}
   * @return string
   */
  function processLine(&$lineParser, &$matches, $tag)
  {
    list($match, $mContent) = $matches;
    //    [1] => ...

    $el = &new TexyScriptElement($this->texy);
    return $el->addTo($lineParser->element);
  }



} // TexyScriptModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 */
class TexyScriptElement extends TexyTextualElement {

  function generateContent()
  {
    return '<--internal use-->';
  }

}  // TexyScriptElement





?>