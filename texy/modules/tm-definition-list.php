<?php

/**
 * ------------------------------------------
 *   DEFINITION LIST - TEXY! DEFAULT MODULE
 * ------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * DEPENDENCES: tm_list.php
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Modules for parsing text into blocks
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
require_once('tm-list.php');




/**
 * DEFINITION LIST MODULE CLASS
 */
class TexyDefinitionListModule extends TexyListModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?'                         // .{color:red}
                                                . '(\S.*)\:\ *MODIFIER_H?\n'                    // Term:
                                                . '(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mU');   //    - description
  }



  /***
   * Callback function (for blocks)
   *
   *            Term: .(title)[class]{style}>
   *              - description 1
   *              - description 2
   *              - description 3
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mModList1, $mModList2, $mModList3, $mModList4,
                 $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4,
                 $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >

    //    [5] => ...
    //    [6] => (title)
    //    [7] => [class]
    //    [8] => {style}
    //    [9] => >

    //   [10] => space
    //   [11] => - * +
    //   [12] => ...
    //   [13] => (title)
    //   [14] => [class]
    //   [15] => {style}
    //   [16] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->type = TEXY_LIST_DEFINITION;
    $el->modifier->setProperties($mModList1, $mModList2, $mModList3, $mModList4);

    $reTerm = '#^(\S.*)\:\ *MODIFIER_H?' . TEXY_NEWLINE .'(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mUA';

    do {
      $mType = preg_quote($mType);
      $spacesBase = strlen($mSpaces);
      $reItem = "#^(\ {1,$spacesBase})$mType\ +(.*)".TEXY_PATTERN_MODIFIER_H."?()$#mA";

      $elItem = &new TexyListItemElement($texy);
      $elItem->type = TEXY_LISTITEM_TERM;
      $el->children[] = & $elItem;
      $elItem->modifier->setProperties($mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4);
      $elItem->parse($mContentTerm);
      if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

      do {
        $elItem = &new TexyListItemElement($texy);
        $elItem->type = TEXY_LISTITEM_DEFINITION;
        $el->children[] = & $elItem;
        $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $content = '';
        $spaces = '';

        do {
          $content .= $mContent . TEXY_NEWLINE;

          if ($blockParser->match("#^(?:|\ \{$spacesBase}(\ {1,$spaces})(.*))()$#Am", $matches)) {
            list($match, $mSpaces, $mContent) = $matches;
            //    [1] => SPACE2
            //    [2] => ...
            if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
            continue;
          }

          break;
        } while (true);

        $elItem->parse($content);
        if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

        if ($blockParser->match($reItem, $matches)) {
          list($match, $mSpaces, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
          //    [1] => SPACE
          //    [2] => ...
          //    [3] => (title)
          //    [4] => [class]
          //    [5] => {style}
          //    [6] => >
          continue;
        }

        break;
      } while (true);

      if ($blockParser->match($reTerm, $matches)) {
        list($match, $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4, $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        continue;
      }

      break;
    } while (true);


    $blockParser->addChildren($el);
  }

} // TexyDefinitionListModule






?>