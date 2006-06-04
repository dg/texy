<?php

/**
 * ----------------------------------------------
 *   PARAGRAPH / GENERIC - TEXY! DEFAULT MODULE
 * ----------------------------------------------
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
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule {




  /***
   * Module initialization
   */
  function init()
  {
    $this->texy->genericBlock = array(&$this, 'processBlock');
  }



  function processBlock(&$blockParser, $content)
  {
    $str_blocks = preg_split('#(\n{2,})#', $content);

    foreach ($str_blocks as $str) {
      $str = trim($str);
      if (!$str) continue;
      $this->processSingleBlock($blockParser, $str);
    }
  }



  /***
   * Callback function (for blocks)
   *
   *            ....  .(title)[class]{style}>
   *             ...
   *             ...
   *
   */
  function &processSingleBlock(&$blockParser, $content)
  {
    preg_match('#^(.+)'.TEXY_PATTERN_MODIFIER_H.'?(\n.*)?()$#sU', $content, $matches);
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >


    // PARAGRAPH or DIV

    $el = &new TexyGenericBlockElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    // ....
    //  ...  => \n
    $mContent = preg_replace('#\n (\S)#', " \r\\1", trim($mContent . $mContent2));
    $mContent = strtr($mContent, "\n\r", " \n");

    $el->parse($mContent);

    // specify tag
    if ($el->contentType == TEXY_CONTENT_TEXTUAL) $el->tag = 'p';
    elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $el->tag = 'div';
    elseif ($el->contentType == TEXY_CONTENT_HTML) $el->tag = '';
    else $el->tag = 'div';

    // add <br />
    if ($el->tag && (strpos($el->content, "\n") !== false)) {
      $elBr = &new TexyLineBreakElement($this->texy);
      $el->content = strtr($el->content,
                        array("\n" => $elBr->addTo($el))
                     );
    }

    $blockParser->addChildren($el);
  }





} // TexyGenericBlockModule








/****************************************************************************
                               TEXY! DOM ELEMENTS                          */



/**
 * HTML ELEMENT LINE BREAK
 */
class TexyLineBreakElement extends TexyTextualElement {
  var $tag = 'br';

} // TexyLineBreakElement




/**
 * HTML ELEMENT PARAGRAPH / DIV / TRANSPARENT
 */
class TexyGenericBlockElement extends TexyTextualElement {
  var $tag = 'p';

} // TexyGenericBlockElement





?>