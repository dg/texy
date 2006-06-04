<?php

/**
 * ----------------------------------------------------------
 *   ORDERED / UNORDERED NESTED LIST - TEXY! DEFAULT MODULE
 * ----------------------------------------------------------
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




// LIST STYLE
define('TEXY_LIST_DEFINITION',       1);
define('TEXY_LIST_UNORDERED',        2);
define('TEXY_LIST_ORDERED',          3);
define('TEXY_LIST_MASK',             3);
define('TEXY_LISTSTYLE_UPPER_ALPHA', 1 << 2);
define('TEXY_LISTSTYLE_LOWER_ALPHA', 2 << 2);
define('TEXY_LISTSTYLE_UPPER_ROMAN', 3 << 2);
define('TEXY_LISTSTYLE_MASK',        3 << 2);

// LIST ITEM TYPES
define('TEXY_LISTITEM',              0);
define('TEXY_LISTITEM_TERM',         1);
define('TEXY_LISTITEM_DEFINITION',   2);


/**
 * ORDERED / UNORDERED NESTED LIST MODULE CLASS
 */
class TexyListModule extends TexyModule {
  var $allowed       = true;                  // generally disable / enable


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?'                                                     // .{color: red}
                                              . '(\*|\-|\+|\d+\.|\d+\)|[a-zA-Z]+\)|[IVX]+\.)\ +(.*)MODIFIER_H?()$#mU');   // - item
  }



  /***
   * Callback function (for blocks)
   *
   *            1) .... .(title)[class]{style}>
   *            2) ....
   *                + ...
   *                + ...
   *            3) ....
   *
   */
  function processBlock(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mModList1, $mModList2, $mModList3, $mModList4, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >

    //    [5] => * + - 1. 1) a) A) IV.
    //    [6] => ...
    //    [7] => (title)
    //    [8] => [class]
    //    [9] => {style}
    //   [10] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->modifier->setProperties($mModList1, $mModList2, $mModList3, $mModList4);
    do {
      $type = '\*';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\-';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\+';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\d+\.';    if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; break; }
      $type = '\d+\)';    if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; break; }
      $type = '[A-Z]+\)'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED | TEXY_LISTSTYLE_UPPER_ALPHA; break; }
      $type = '[a-z]+\)'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED | TEXY_LISTSTYLE_LOWER_ALPHA; break; }
      $type = '[IVX]+\.'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED | TEXY_LISTSTYLE_UPPER_ROMAN; break; }
    } while (false);


    $reItem = "#^$type\ +(.*)".TEXY_PATTERN_MODIFIER_H.'?()$#AUm';


    do {
      $elItem = &new TexyListItemElement($texy);
      $el->children[] = & $elItem;
      $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
      $content = ' ';             // trick: don't recognize `- 12. 3.` as three nested lists
      $spaces = '';
//      $mContent .= TEXY_NEWLINE;  // trick: don't recognize IXV. as second line of paragraph

      do {
        $content .= $mContent . TEXY_NEWLINE;

        if ($blockParser->match("#^(?:|(\ {1,$spaces})(.*))()$#Am", $matches)) {
          list($match, $mSpaces, $mContent) = $matches;
          //    [1] => SPACES
          //    [2] => ...
          if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
          continue;
        }

        break;
      } while (true);

      $elItem->parse($content);
      if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

      if (!$blockParser->match($reItem, $matches)) break;
      list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

    } while (true);


    $blockParser->addChildren($el);
  }




} // TexyListModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT OL / UL / DL
 */
class TexyListElement extends TexyBlockElement {
  var $type;


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);

    $tags = array(TEXY_LIST_UNORDERED => 'ul', TEXY_LIST_ORDERED => 'ol', TEXY_LIST_DEFINITION => 'dl');
    $tag = $tags[$this->type & TEXY_LIST_MASK];

    $styles = array(0 => '', TEXY_LISTSTYLE_UPPER_ALPHA => 'list-style-type:upper-alpha;', TEXY_LISTSTYLE_LOWER_ALPHA => 'list-style-type:lower-alpha;', TEXY_LISTSTYLE_UPPER_ROMAN => 'list-style-type:upper-roman;');

    if (!isset($attr['style'])) $attr['style'] = '';
    $attr['style'] .= $styles[$this->type & TEXY_LISTSTYLE_MASK];
  }

} // TexyListElement





/**
 * HTML ELEMENT LI / DL / DT
 */
class TexyListItemElement extends TexyBlockElement {
  var $type;


  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);

    $tags = array(TEXY_LISTITEM => 'li', TEXY_LISTITEM_TERM => 'dt', TEXY_LISTITEM_DEFINITION => 'dd');
    $tag = $tags[$this->type & 3]; // NOT GOOD!
  }
} // TexyListItemElement





?>