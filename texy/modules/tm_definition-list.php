<?php

/**
 * ------------------------------------------
 *   DEFINITION LIST - TEXY! DEFAULT MODULE
 * ------------------------------------------
 *
 * Version 0.9 beta
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
require_once('tm_list.php');




/**
 * DEFINITION LIST MODULE CLASS
 */
class TexyDefinitionListModule extends TexyListModule {


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock', '#^(\S.*)\:\ *MODIFIER_H?' . TEXY_NEWLINE
                                               .'(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mU');
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
  function &processBlock(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4, $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >

    //    [6] => space
    //    [7] => - * +
    //    [8] => ...
    //    [9] => (title)
    //    [10] => [class]
    //    [11] => {style}
    //    [12] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->type = TEXY_LIST_DEFINITION;
    $el->modifier->copyFrom($blockParser->modifier);

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


    return $el;
  }

} // TexyDefinitionListModule






?>