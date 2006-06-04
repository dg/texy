<?php

/**
 * ----------------------------------
 *   HEADING - TEXY! DEFAULT MODULE
 * ----------------------------------
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




define('TEXY_HEADING_DYNAMIC',         1);  // auto-leveling
define('TEXY_HEADING_FIXED',           2);  // fixed-leveling




/**
 * HEADING MODULE CLASS
 */
class TexyHeadingModule extends TexyModule {
  // options
  var $allowed = true;                  // generally disable / enable
  var $top    = 1;                      // number of top heading, 1 - 6
  var $title;                           // textual content of first heading
  var $balancing = TEXY_HEADING_DYNAMIC;
  var $balanceDelta = array(            // when $balancing = TEXY_HEADING_FIXED
                         '#' => 0,      //   #  -->  $balanceDelta['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
                         '*' => 1,
                         '=' => 2,
                         '-' => 3,
                         7   => 0,
                         6   => 1,
                         5   => 2,
                         4   => 3,
                         3   => 4,
                         2   => 5,
                       );

  // private
  var $rangeUnderline;
  var $deltaUnderline;
  var $rangeSurround;
  var $deltaSurround;



  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlockUnderline', '#^(\S.*)MODIFIER_H?' . TEXY_NEWLINE
                                                        .'(\#|\*|\=|\-){3,}$#mU');
    $this->registerBlockPattern('processBlockSurround',  '#^((\#|\=){2,7})(?!\\2)(.*)MODIFIER_H?\\2*()$#mU');
  }


  /***
   * Preprocessing
   */
  function preProcess(&$text) {
    $this->rangeUnderline = array(10, 0);
    $this->rangeSurround    = array(10, 0);
    unset($this->deltaUnderline);
    unset($this->deltaSurround);
  }


  /***
   * Callback function (for blocks)
   *
   *            Heading .(title)[class]{style}>
   *            -------------------------------
   *
   */
  function processBlockUnderline(&$blockParser, &$matches) {
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

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $this->balanceDelta[$mLine];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->deltaUnderline;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->rangeUnderline[0] = min($this->rangeUnderline[0], $el->level);
    $this->rangeUnderline[1] = max($this->rangeUnderline[1], $el->level);
    $this->deltaUnderline    = -$this->rangeUnderline[0];
    $this->deltaSurround     = -$this->rangeSurround[0] + ($this->rangeUnderline[1] ? ($this->rangeUnderline[1] - $this->rangeUnderline[0] + 1) : 0);

    $blockParser->addChildren($el);
  }



  /***
   * Callback function (for blocks)
   *
   *            ### Heading .(title)[class]{style}>
   *
   */
  function processBlockSurround(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ###
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $this->balanceDelta[strlen($mLine)];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->deltaSurround;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->rangeSurround[0] = min($this->rangeSurround[0], $el->level);
    $this->rangeSurround[1] = max($this->rangeSurround[1], $el->level);
    $this->deltaSurround    = -$this->rangeSurround[0] + ($this->rangeUnderline[1] ? ($this->rangeUnderline[1] - $this->rangeUnderline[0] + 1) : 0);

    $blockParser->addChildren($el);
  }




} // TexyHeadingModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyTextualElement {
  var $parentModule;
  var $level = 0;        // 0 .. ?
  var $deltaLevel = 0;


  // constructor
  function TexyHeadingElement(&$texy) {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyHeadingModule'];
  }


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);
    $tag = 'h' . min(6, max(1, $this->level + $this->deltaLevel + $this->parentModule->top));
  }

} // TexyHeadingElement









?>