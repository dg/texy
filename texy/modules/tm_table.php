<?php

/**
 * --------------------------------
 *   TABLE - TEXY! DEFAULT MODULE
 * --------------------------------
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
 * TABLE MODULE CLASS
 */
class TexyTableModule extends TexyModule {
  var $allowed       = true;                  // generally disable / enable


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_HV\n)?'      // .{color: red}
                                              . '(\|.*)$#mU');              // | ....
  }





  /***
   * Callback function (for blocks)
   *
   *            .(title)[class]{style}>
   *            |------------------
   *            | xxx | xxx | xxx | .(..){..}[..]
   *            |------------------
   *            | aa  | bb  | cc  |
   *
   */
  function &processBlock(&$blockParser, &$matches) {
    if (!$this->allowed) return false;
    list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5, $mRow) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >
    //    [5] => _
    //    [6] => | ....

    $texy = & $this->texy;
    $el = &new TexyTableElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
    $el->colsCount = 0;

    $head = false;
    $colModifier = array();
    $elField = null;
    $elRow = null;

    preg_match('#^\|(.+)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#U', $mRow, $matches);
    do {
      list($match, $mContent, $mModRow1, $mModRow2, $mModRow3, $mModRow4, $mModRow5) = $matches;
      //    [1] => ....
      //    [2] => (title)
      //    [3] => [class]
      //    [4] => {style}
      //    [5] => >
      //    [6] => _

      if (preg_match('#\|\-{3,}$#AU', $match)) {
        $head = !$head;
        continue;
      }

      $elLastRow = & $elRow->children;
      $elRow = &new TexyTableRowElement($texy);
      $elRow->modifier->setProperties($mModRow1, $mModRow2, $mModRow3, $mModRow4, $mModRow5);
      $elRow->isHead = $head;
      $el->children[] = & $elRow;

      $cols = explode('|', $mContent);
      $col = 0;
      foreach ($cols as $key => $s) {
        $col++;

        if (($s == '') && $elField) { // colspan
          $elField->colSpan++;
          continue;
        }

        if (!preg_match('#(?-U)(\*|\^)?\ *'.TEXY_PATTERN_MODIFIER_HV.'?(?U)(.*)'.TEXY_PATTERN_MODIFIER_HV.'?\ *()$#AU', $s, $matchesC)) break;
        list($match, $mHead, $mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matchesC;
        //    [1] => * ^
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => <
        //    [6] => ^
        //    [7] => ....
        //    [8] => (title)
        //    [9] => [class]
        //    [10] => {style}
        //    [11] => <>
        //    [12] => ^

        if (($mHead == '^') && $elLastRow) {  // rowspan
          if (isset($elLastRow[$col]))  $elLastRow[$col]->rowSpan++;
          continue;
        }

        if (!isset($colModifier[$col])) { $colModifier[$col] = &$texy->createModifier(); }
        if ($mModCol1 || $mModCol2 || $mModCol3 || $mModCol4 || $mModCol5) {
          $colModifier[$col]->clear();
          $colModifier[$col]->setProperties($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
        }

        $elField = &new TexyTableFieldElement($texy);
        $elField->isHead = ($head || ($mHead == '*'));
        $elField->modifier->copyFrom($colModifier[$col]);
        $elField->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        $elField->parse($mContent);
        $elRow->children[$col] = & $elField;
      } // foreach

      $el->colsCount = max($el->colsCount, $col);

    } while ($blockParser->match('#^\|(.+)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#mUA', $matches));

    return $el;
  }



} // TexyTableModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT TABLE
 */
class TexyTableElement extends TexyBlockElement {
  var $tag = 'table';

} // TexyTableElement






/**
 * HTML ELEMENT TR
 */
class TexyTableRowElement extends TexyBlockElement {
  var $tag = 'tr';
  var $isHead;  // not used yet

} // TexyTableRowElement






/**
 * HTML ELEMENT TD / TH
 */
class TexyTableFieldElement extends TexyInlineElement {
  var $colSpan = 1;
  var $rowSpan = 1;
  var $isHead;

  function generateTag(&$tag, &$attr) {
    parent::generateTag($tag, $attr);
    $tag = $this->isHead ? 'th' : 'td';
    if ($this->colSpan <> 1) $attr['colspan'] = (int) $this->colSpan;
    if ($this->rowSpan <> 1) $attr['rowspan'] = (int) $this->rowSpan;
  }

} // TexyTableFieldElement







?>