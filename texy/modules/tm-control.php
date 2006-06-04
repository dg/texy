<?php

/**
 * ----------------------------------------
 *   FORM CONTROLS - TEXY! DEFAULT MODULE
 * ----------------------------------------
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
 * CONTROL MODULE CLASS - EXPERIMENTAL !!!
 */
class TexyControlModule extends TexyModule {
  var $allowed = array(
         'text'      => true,
         'select'    => true,
         'radio'     => true,
         'checkbox'  => true,
         'button'    => true,
      );


  // constructor
  function TexyControlModule(&$texy)
  {
    parent::TexyModule($texy);
  }


  /***
   * Module initialization.
   */
  function init()
  {
  }


  function trustMode()
  {
  }



  function safeMode()
  {
  }


} // TexyControlModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




?>