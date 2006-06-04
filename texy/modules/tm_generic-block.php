<?php

/**
 * ----------------------------------------------
 *   PARAGRAPH / GENERIC - TEXY! DEFAULT MODULE
 * ----------------------------------------------
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
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock', '#^(.*)MODIFIER_H?(?<!^)()$#mU');
  }




  /***
   * Callback function (for blocks)
   *
   *            ....  .(title)[class]{style}>
   *             ...
   *             ...
   *
   */
  function &processBlock(&$blockParser, &$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >


    if ($match == '') return;  // BLANK LINE

    if ($mContent == '') {     // MODIFIER LINE     .(title)[class]{style}<
      $blockParser->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
      $blockParser->modifierJustUpdated = 1;
      return;
    }

    // PARAGRAPH or DIV

    $el = &new TexyGenericBlockElement($this->texy);
    $el->modifier->copyFrom($blockParser->modifier);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    $text = trim($mContent);
    while ($blockParser->match('#^(['.TEXY_CHAR.'].*| \S.*)$#mUA'.TEXY_PATTERN_UTF, $matches)) {
      if ($matches[1]{0} == ' ') {
        $br = &new TexyLineBreakElement($this->texy);
        $text .= $br->hash($el) . trim($matches[1]);
      } else {
        $text .= ' ' . trim($matches[1]);
      }
    }

    $el->parse($text);
    if ($el->textualContent) $el->tag = 'p';
    elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4 || $mMod5) $el->tag = 'div';
    else $el->tag = '';

    return $el;
  }





} // TexyGenericBlockModule








/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT LINE BREAK
 */
class TexyLineBreakElement extends TexyInlineElement {
  var $tag = 'br';

} // TexyLineBreakElement




/**
 * HTML ELEMENT PARAGRAPH / DIV / TRANSPARENT
 */
class TexyGenericBlockElement extends TexyInlineElement {
  var $tag = 'p';

} // TexyGenericBlockElement





?>