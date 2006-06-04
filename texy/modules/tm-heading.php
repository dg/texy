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
  var $allowed;

  // options
  var $top    = 1;                      // number of top heading, 1 - 6
  var $title;                           // textual content of first heading
  var $balancing = TEXY_HEADING_DYNAMIC;
  var $levels = array(            // when $balancing = TEXY_HEADING_FIXED
                         '#' => 0,      //   #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
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
  var $_rangeUnderline;
  var $_deltaUnderline;
  var $_rangeSurround;
  var $_deltaSurround;



  // constructor
  function TexyHeadingModule(&$texy)
  {
    parent::TexyModule($texy);

    $this->allowed->surrounded  = true;
    $this->allowed->underlined  = true;
  }


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed->underlined)
      $this->registerBlockPattern('processBlockUnderline', '#^(\S.*)MODIFIER_H?\n'
                                                          .'(\#|\*|\=|\-){3,}$#mU');
    if ($this->allowed->surrounded)
      $this->registerBlockPattern('processBlockSurround',  '#^((\#|\=){2,7})(?!\\2)(.*)\\2*MODIFIER_H?()$#mU');
  }



  function preProcess(&$text)
  {
    $this->_rangeUnderline = array(10, 0);
    $this->_rangeSurround    = array(10, 0);
    unset($this->_deltaUnderline);
    unset($this->_deltaSurround);
  }




  /***
   * Callback function (for blocks)
   *
   *            Heading .(title)[class]{style}>
   *            -------------------------------
   *
   */
  function processBlockUnderline(&$blockParser, &$matches)
  {
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
    $el->level = $this->levels[$mLine];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->_deltaUnderline;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    $blockParser->addChildren($el);

    // document title
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->_rangeUnderline[0] = min($this->_rangeUnderline[0], $el->level);
    $this->_rangeUnderline[1] = max($this->_rangeUnderline[1], $el->level);
    $this->_deltaUnderline    = -$this->_rangeUnderline[0];
    $this->_deltaSurround     = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);
  }



  /***
   * Callback function (for blocks)
   *
   *            ### Heading .(title)[class]{style}>
   *
   */
  function processBlockSurround(&$blockParser, &$matches)
  {
    list($match, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ###
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $this->levels[strlen($mLine)];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->_deltaSurround;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    $blockParser->addChildren($el);

    // document title
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->_rangeSurround[0] = min($this->_rangeSurround[0], $el->level);
    $this->_rangeSurround[1] = max($this->_rangeSurround[1], $el->level);
    $this->_deltaSurround    = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);

  }




} // TexyHeadingModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyTextualElement {
  var $level = 0;        // 0 .. ?
  var $deltaLevel = 0;


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    $tag = 'h' . min(6, max(1, $this->level + $this->deltaLevel + $this->texy->headingModule->top));
  }


} // TexyHeadingElement









?>