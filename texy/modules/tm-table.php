<?php

/**
 * --------------------------------
 *   TABLE - TEXY! DEFAULT MODULE
 * --------------------------------
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
 * TABLE MODULE CLASS
 */
class TexyTableModule extends TexyModule {
  var $oddClass     = '';
  var $evenClass    = '';

  // private
  var $isHead;
  var $colModifier;
  var $last;
  var $row;



  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_HV\n)?'      // .{color: red}
                                              . '\|.*()$#mU');                // | ....
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
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >
    //    [5] => _

    $texy = & $this->texy;
    $el = &new TexyTableElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
    $blockParser->addChildren($el);

    $blockParser->moveBackward();

    if ($blockParser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_PATTERN_MODIFIER_H.'?()$#Um', $matches)) {
      list($match, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
      //    [1] => # / =
      //    [2] => ....
      //    [3] => (title)
      //    [4] => [class]
      //    [5] => {style}
      //    [6] => >

      $el->caption = &new TexyTextualElement($texy);
      $el->caption->tag = 'caption';
      $el->caption->parse($mContent);
      $el->caption->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    }

    $this->isHead = false;
    $this->colModifier = array();
    $this->last = array();
    $this->row = 0;

    while (true) {
      if ($blockParser->receiveNext('#^\|\-{3,}$#Um', $matches)) {
        $this->isHead = !$this->isHead;
        continue;
      }

      if ($elRow = &$this->processRow($blockParser)) {
        $el->children[$this->row++] = & $elRow;
        continue;
      }

      break;
    }
  }






  function &processRow(&$blockParser) {
    $texy = & $this->texy;

    if (!$blockParser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#U', $matches)) return false;
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
    //    [1] => ....
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => _

    $elRow = &new TexyTableRowElement($this->texy);
    $elRow->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
    if ($this->row % 2 == 0) {
      if ($this->oddClass) $elRow->modifier->classes[] = $this->oddClass;
    } else {
      if ($this->evenClass) $elRow->modifier->classes[] = $this->evenClass;
    }

    $col = 0;
    $elField = null;
    foreach (explode('|', $mContent) as $field) {
      if (($field == '') && $elField) { // colspan
        $elField->colSpan++;
        unset($this->last[$col]);
        $col++;
        continue;
      }

      $field = rtrim($field);
      if ($field == '^') { // rowspan
        if (isset($this->last[$col])) {
          $this->last[$col]->rowSpan++;
          $col += $this->last[$col]->colSpan;
          continue;
        }
      }

      if (!preg_match('#(?-U)(\*?)\ *'.TEXY_PATTERN_MODIFIER_HV.'?(?U)(.*)'.TEXY_PATTERN_MODIFIER_HV.'?()$#AU', $field, $matches)) continue;
      list($match, $mHead, $mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
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

      if ($mModCol1 || $mModCol2 || $mModCol3 || $mModCol4 || $mModCol5) {
        $this->colModifier[$col] = &$texy->createModifier();
        $this->colModifier[$col]->setProperties($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
      }

      $elField = &new TexyTableFieldElement($texy);
      $elField->isHead = ($this->isHead || ($mHead == '*'));
      if (isset($this->colModifier[$col]))
        $elField->modifier->copyFrom($this->colModifier[$col]);
      $elField->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
      $elField->parse($mContent);
      $elRow->children[$col] = & $elField;
      $this->last[$col] = & $elField;
      $col++;
    }

    return $elRow;
  }


} // TexyTableModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT TABLE
 */
class TexyTableElement extends TexyBlockElement {
  var $tag = 'table';
  var $caption;

  function generateContent()
  {
    $html = parent::generateContent();

    if ($this->caption)
      $html = $this->caption->toHTML() . $html;

    return $html;
  }


} // TexyTableElement






/**
 * HTML ELEMENT TR
 */
class TexyTableRowElement extends TexyBlockElement {
  var $tag = 'tr';

} // TexyTableRowElement






/**
 * HTML ELEMENT TD / TH
 */
class TexyTableFieldElement extends TexyTextualElement {
  var $colSpan = 1;
  var $rowSpan = 1;
  var $isHead;

  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    $tag = $this->isHead ? 'th' : 'td';
    if ($this->colSpan <> 1) $attr['colspan'] = (int) $this->colSpan;
    if ($this->rowSpan <> 1) $attr['rowspan'] = (int) $this->rowSpan;
  }

} // TexyTableFieldElement







?>