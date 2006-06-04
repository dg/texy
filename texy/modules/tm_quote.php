<?php

/**
 * -------------------------------------
 *   BLOCKQUOTE - TEXY! DEFAULT MODULE
 * -------------------------------------
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
 * BLOCKQUOTE MODULE CLASS
 */
class TexyBlockQuoteModule extends TexyModule {
  var $allowed = true;                  // generally disable / enable


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?>(\ +|:)(\S.*)$#mU');
    $this->registerLinePattern('processLine', '#(?<!\>)(\>\>)(?!\ |\>)(.+)MODIFIER?(?<!\ |\<)\<\<(?!\<)'.TEXY_PATTERN_LINK.'?()#U', 'q');
  }


  /***
   * Callback function: >>.... .(title)[class]{style}<<:LINK
   * @return string
   */
  function processLine(&$lineParser, &$matches, $tag) {
    list($match, $mMark, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => **
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => LINK

    if (!$this->allowed) return $match;

    $texy = & $this->texy;
    $el = &new TexyQuoteElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

    if ($mLink) {
      $el->cite = & $texy->createURL();
      $el->cite->set($mLink);
    }
    return $el->hash($lineParser->element, $mContent);
  }




  /***
   * Callback function (for blocks)
   *
   *            > They went in single file, running like hounds on a strong scent,
   *            and an eager light was in their eyes. Nearly due west the broad
   *            swath of the marching Orcs tramped its ugly slot; the sweet grass
   *            of Rohan had been bruised and blackened as they passed.
   *            >:http://www.mycom.com/tolkien/twotowers.html
   *
   */
  function &processBlock(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mSpaces, $mContent) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => <>
    //    [5] => spaces |
    //    [6] => ... / LINK

    $texy = & $this->texy;
    $el = &new TexyBlockQuoteElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    $content = '';
    $linkTarget = '';
    $spaces = '';
    do {
      if ($mSpaces == ':') $linkTarget = $mContent;
      else {
        if ($spaces === '') $spaces = strlen($mSpaces);
        $content .= $mContent . TEXY_NEWLINE;
      }

      if (!$blockParser->match("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
      list($match, $mSpaces, $mContent) = $matches;
    } while (true);

    if ($linkTarget) {
      $el->cite = & $texy->createURL();
      $el->cite->set($linkTarget);
    }

    $el->parse($content);

    return $el;
  }



} // TexyBlockQuoteModule





/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT BLOCKQUOTE
 */
class TexyBlockQuoteElement extends TexyBlockElement {
  var $tag = 'blockquote';
  var $cite;


  function TexyBlockQuoteElement(&$texy) {
    parent::TexyBlockElement($texy);
    $this->cite = & $texy->createURL();
  }


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);
    if ($this->cite->URL) $attr['cite'] = $this->cite->URL;
  }

} // TexyBlockQuoteElement





/**
 * HTML TAG QUOTE
 */
class TexyQuoteElement extends TexyInlineTagElement {
  var $tag = 'q';
  var $cite;


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);
    if ($this->cite->URL) $attr['cite'] = $this->cite->URL;
  }


} // TexyBlockQuoteElement






?>