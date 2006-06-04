<?php

/**
 * ----------------------------------
 *   HEADING - TEXY! DEFAULT MODULE
 * ----------------------------------
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
 * HEADING MODULE CLASS
 */
class TexyHeadingModule extends TexyModule {
  // options
  var $allowed = true;                  // generally disable / enable
  var $top    = 1;                      // number of top heading, 1 - 6
  var $title;                           // textual content of first heading

  // private
  var $rangeSetext;
  var $deltaSetext;
  var $rangeATX;
  var $deltaATX;



  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlockSetext', '#^(\S.*)MODIFIER_H?' . TEXY_NEWLINE
                                                     .'(\#|\*|\=|\-){3,}$#mU');
    $this->registerBlockPattern('processBlockATX',    '#^((\#|\=){2,7})(?!\\2)(.*)MODIFIER_H?\\2*()$#mU');
  }


  /***
   * Preprocessing
   */
  function preProcess(&$text) {
    $this->rangeSetext = array(10, 0);
    $this->rangeATX    = array(10, 0);
    unset($this->deltaSetext);
    unset($this->deltaATX);
  }


  /***
   * Callback function (for blocks)
   *
   *            Heading .(title)[class]{style}>
   *            -------------------------------
   *
   */
  function processBlockSetext(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mLine) = $matches;
    //  $matches:
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //
    //    [6] => ...

    $sizes = array('#' => 1, '*' => 2, '=' => 3, '-' => 4);

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $sizes[$mLine];
    $el->deltaLevel = & $this->deltaSetext;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    $this->rangeSetext[0] = min($this->rangeSetext[0], $el->level);
    $this->rangeSetext[1] = max($this->rangeSetext[1], $el->level);
    $this->deltaSetext = -$this->rangeSetext[0];
    $this->deltaATX    = -$this->rangeATX[0] + ($this->rangeSetext[1] ? ($this->rangeSetext[1] - $this->rangeSetext[0] + 1) : 0);

    return $el;
  }



  /***
   * Callback function (for blocks)
   *
   *            ### Heading .(title)[class]{style}>
   *
   */
  function processBlockATX(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ###
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $el = &new TexyHeadingElement($this->texy);
    $el->level = 8 - strlen($mLine);
    $el->deltaLevel = & $this->deltaATX;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    $this->rangeATX[0] = min($this->rangeATX[0], $el->level);
    $this->rangeATX[1] = max($this->rangeATX[1], $el->level);
    $this->deltaATX    = -$this->rangeATX[0] + ($this->rangeSetext[1] ? ($this->rangeSetext[1] - $this->rangeSetext[0] + 1) : 0);

    return $el;
  }




} // TexyHeadingModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyInlineElement {
  var $parentModule;
  var $level;
  var $deltaLevel;


  // constructor
  function TexyHeadingElement(&$texy) {
    parent::TexyInlineElement($texy);
    $this->parentModule = & $texy->modules['TexyHeadingModule'];
  }


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);
    $tag = 'h' . min(6, max(0, $this->level + $this->deltaLevel + $this->parentModule->top));
  }

} // TexyHeadingElement









?>