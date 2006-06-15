<?php

/**
 * -------------------------------
 *   TEXY! DEFAULT BLOCK MODULES
 * -------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
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











/**
 * HEADING block module
 * --------------------
 *
 *     A First Level Header
 *     ####################
 *
 *     A Second Level Header
 *     *********************
 *
 *     ###### A First Level Header
 *
 *     ### A Fourth Level Header
 *
 */

class TexyHeadingModule extends TexyModule {
  var $rangeSetext;
  var $deltaSetext;
  var $rangeATX;
  var $deltaATX;



  function init() {
    $this->registerBlockPattern('blockProcessSetext', '#^(\S.*)MODIFIER_H?' . TEXY_NEWLINE
                                                     .'(\#|\*|\=|\-){3,}$#mU');
    $this->registerBlockPattern('blockProcessATX',    '#^(\#{1,6})(?!\#)(.*)MODIFIER_H?()$#mU');
  }


  function preProcess() {
    $this->rangeSetext = array(10, 0);
    $this->rangeATX    = array(10, 0);
    unset($this->deltaSetext);
    unset($this->deltaATX);
  }


  // Heading .(title)[class]{style}>
  // -------------------------------
  //
  function blockProcessSetext(&$block, &$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mLine) = $matches;
    //  $matches:
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //
    //    [6] => ...

    $sizes = array('#' => 1, '*' => 2, '=' => 3, '-' => 4);

    $el = &new TexyHeaderElement($this->texy, $sizes[$mLine]);
    $el->deltaLevel = & $this->deltaSetext;
    $el->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
    $el->children = &$this->texy->parseInline(trim($mContent));

    $this->rangeSetext[0] = min($this->rangeSetext[0], $el->level);
    $this->rangeSetext[1] = max($this->rangeSetext[1], $el->level);   
    $this->deltaSetext = -$this->rangeSetext[0];
    $this->deltaATX    = -$this->rangeATX[0] + ($this->rangeSetext[1] ? ($this->rangeSetext[1] - $this->rangeSetext[0] + 1) : 0);

    $block->children[] = & $el;
  }



  // ### Heading .(title)[class]{style}>
  //
  function blockProcessATX(&$block, &$matches) {
    list($match, $mLine, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ###
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $el = &new TexyHeaderElement($this->texy, 7 - strlen($mLine));
    $el->deltaLevel = & $this->deltaATX;
    $el->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
    $el->children = &$this->texy->parseInline(trim($mContent));

    $this->rangeATX[0] = min($this->rangeATX[0], $el->level);
    $this->rangeATX[1] = max($this->rangeATX[1], $el->level);
    $this->deltaATX    = -$this->rangeATX[0] + ($this->rangeSetext[1] ? ($this->rangeSetext[1] - $this->rangeSetext[0] + 1) : 0);

    $block->children[] = & $el;
  }


} // TexyHeadingModule
















/**
 * HORIZONTAL LINE block module
 * ----------------------------
 *
 * ---------------------------
 *
 * - - - - - - - - - - - - - - 
 *
 * ***************************
 *
 * * * * * * * * * * * * * * * 
 */
class TexyHorizlineModule extends TexyModule {


  function init() {
    $this->registerBlockPattern('blockProcess', '#^(\- |\-|\* |\*){3,}\ *MODIFIER_H?()$#mU');
  }


  function blockProcess(&$block, &$matches) {
    list($match, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ---
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >

    $el = &new TexyHorizLineElement($this->texy);
    $el->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
    $block->children[] = & $el;
  }

} // TexyHorizlineModule





/**
 * BLOCKQUOTE block module
 * -----------------------
 *
 *     > They went in single file, running like hounds on a strong scent,
 *     and an eager light was in their eyes. Nearly due west the broad
 *     swath of the marching Orcs tramped its ugly slot; the sweet grass
 *     of Rohan had been bruised and blackened as they passed.
 *     >:http://www.mycom.com/tolkien/twotowers.html
 */

class TexyBlockQuoteModule extends TexyModule {


  function init() {
    $this->registerBlockPattern('blockProcess', '#^>(\ +|:)(\S.*)$#mU');
  }


  function blockProcess(&$block, &$matches) {
    list($match, $mSpaces, $mContent) = $matches;
    //    [1] => spaces |
    //    [2] => ... / LINK

    $texy = & $this->texy;
    $el = &new TexyQuoteElement($texy);
    $el->block = true;
    $el->modifier->copyFrom($block->modifier);

    $content = '';
    $linkTarget = '';
    $spaces = '';
    do {
      if ($mSpaces == ':') $linkTarget = $mContent;
      else {
        if ($spaces === '') $spaces = strlen($mSpaces);
        $content .= $mContent . TEXY_NEWLINE;
      }
      
      if (!$block->match("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
      list($match, $mSpaces, $mContent) = $matches;      
    } while (true);

    if ($linkTarget) { 
      $el->cite = & $texy->createURL();
      $el->cite->set($linkTarget);
    }

    $el->children = &$texy->parseBlock($content);

    $block->children[] = & $el;
  }

} // TexyBlockQuoteModule








/**
 * ORDERED / UNORDERED NESTED LIST block module
 * --------------------------------------------
 *
 *     1) .... .(title)[class]{style}>
 *     2) ....
 *         + ...
 *         + ...
 *     3) ....
 */

class TexyListModule extends TexyModule {
  

  function init() {
    $this->registerBlockPattern('blockProcess', '#^(\*|\-|\+|\d+\.|\d+\)|[a-zA-Z]+\)|[IVX]+\.)\ +(.*)MODIFIER_H?()$#mU');
  }


  function blockProcess(&$block, &$matches) {
    list($match, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => * + - 1. 1) a) A) IV.
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->modifier->copyFrom($block->modifier);
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
      $elItem->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
      $content = '';
      $spaces = '';

      do {
        $content .= $mContent . TEXY_NEWLINE;

        if ($block->match("#^(?:|(\ {1,$spaces})(.*))()$#Am", $matches)) {
          list($match, $mSpaces, $mContent) = $matches;
          //    [1] => SPACES
          //    [2] => ...
          if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
          continue;
        }

        break;
      } while (true);

      $elItem->children = &$texy->parseBlock($content);
      if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

      if (!$block->match($reItem, $matches)) break;
      list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

    } while (true);


    $block->children[] = & $el;
  }
  
} // TexyListModule










/**
 * DEFINITION LIST block module
 * ----------------------------
 *
 *     Term: .(title)[class]{style}>
 *       - description 1
 *       - description 2
 *       - description 3   
 */
class TexyDefinitionListModule extends TexyModule {
  

  function init() {
    $this->registerBlockPattern('blockProcess', '#^(\S.*)\:\ *MODIFIER_H?' . TEXY_NEWLINE
                                               .'(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mU');
  }


  function blockProcess(&$block, &$matches) {
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
    $el->modifier->copyFrom($block->modifier);

    $reTerm = '#^(\S.*)\:\ *MODIFIER_H?' . TEXY_NEWLINE .'(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mUA';

    do {
      $mType = preg_quote($mType);
      $spacesBase = strlen($mSpaces);
      $reItem = "#^(\ {1,$spacesBase})$mType\ +(.*)".TEXY_PATTERN_MODIFIER_H."?()$#mA";

      $elItem = &new TexyListItemElement($texy);
      $elItem->type = TEXY_LISTITEM_TERM;
      $el->children[] = & $elItem;
      $elItem->modifier->setProps($mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4);
      $elItem->children = &$texy->parseInline($mContentTerm);

      do {
        $elItem = &new TexyListItemElement($texy);
        $elItem->type = TEXY_LISTITEM_DEFINITION;
        $el->children[] = & $elItem;
        $elItem->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
        $content = '';
        $spaces = '';

        do {
          $content .= $mContent . TEXY_NEWLINE;

          if ($block->match("#^(?:|\ \{$spacesBase}(\ {1,$spaces})(.*))()$#Am", $matches)) {
            list($match, $mSpaces, $mContent) = $matches;
            //    [1] => SPACE2
            //    [2] => ...
            if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
            continue;
          }

          break;
        } while (true);

        $elItem->children = &$texy->parseBlock($content);
        if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

        if ($block->match($reItem, $matches)) {
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

      if ($block->match($reTerm, $matches)) {
        list($match, $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4, $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        continue;
      }

      break;
    } while (true);


    $block->children[] = & $el;
  }
  
} // TexyDefinitionListModule









/**
 * TABLE block module
 * ------------------
 */
class TexyTableModule extends TexyModule {


  function init() {
    $this->registerBlockPattern('blockProcess', '#^\|(.+)(?:|\|\ *MODIFIER_HV?)()$#mU');
  }




  function blockProcess(&$block, &$matches) {
    $texy = & $this->texy;
    $el = &new TexyTableElement($texy);
    $el->modifier->copyFrom($block->modifier);
    $el->colsCount = 0;

    $head = false;
    $colModifier = array();

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

      $elRow = &new TexyTableRowElement($texy);
      $elRow->modifier->setProps($mModRow1, $mModRow2, $mModRow3, $mModRow4, $mModRow5);
      $elRow->isHead = $head;
      $el->children[] = & $elRow;

      $cols = explode('|', $mContent);
      $col = 0;
      foreach ($cols as $key => $s) {
        if (!preg_match('#(?-U)(\*)?\ *'.TEXY_PATTERN_MODIFIER_HV.'?(?U)(.*)'.TEXY_PATTERN_MODIFIER_HVS.'?\ *()$#AU', $s, $matchesC)) break;
        list($match, $mHead, $mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5, $mSpan) = $matchesC;
        //    [1] => *
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
        //    [13] => 1/2

        if (!isset($colModifier[$col])) { $colModifier[$col] = &new TexyModifier($texy); }
        if ($mModCol1 || $mModCol2 || $mModCol3 || $mModCol4 || $mModCol5) {
          $colModifier[$col]->clear();
          $colModifier[$col]->setProps($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
        }

        $elField = &new TexyTableFieldElement($texy);
        $elField->isHead = ($head || $mHead);
        $elField->modifier->copyFrom($colModifier[$col]);
        $elField->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        $elField->children = &$texy->parseInline($mContent);
        $elRow->children[] = & $elField;

        if ($mSpan) {
          $span = explode('/', $mSpan);
          $elField->colSpan = max($span[0], 1);
          $elField->rowSpan = max($span[1], 1);
          $col += $elField->colSpan;
        } else $col++;
      }
      $el->colsCount = max($el->colsCount, $col);

    } while ($block->match('#^\|(.+)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#mUA', $matches));

    $block->children[] = & $el;
  }
  
} // TexyTableModule










/**
 * CODE block & inline module
 * --------------------------
 */
class TexyCodeModule extends TexyModule {
  var $functions;
  

  function init() {
    $this->functions['default'] = array(&$this, 'defaultFunc');

    $this->registerBlockPattern('processBlock',   '#^`(.*\n.*)MODIFIER?`()$#mUs');
    $this->registerInlinePattern('processInline', '#\`(\S.*)MODIFIER?(?<!\ )\`()#U');
  }



  function &buildElement(&$matches, $isBlock) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >

    $el = &new TexyCodeElement($this->texy);
    $el->block = $isBlock;
    $el->modifier->setProps($mMod1, $mMod2, $mMod3);
    $el->content = $mContent;

    if ($el->modifier->classes) {
      $func = $el->modifier->classes[0]; 
      if (!isset($this->functions[$func])) $func = 'default';
    } else $func = 'default';
    
    call_user_func_array($this->functions[$func], array(&$el->content, &$el->tag, $el->block));

    return $el;
  }


  function processBlock(&$block, &$matches) {
    $block->children[] = & $this->buildElement($matches, true);
  }


  function processInline(&$matches) {
    $el = & $this->buildElement($matches, false);
    return array(&$el);
  }


  function defaultFunc(&$str, &$tag, $block) {
    $str = nl2br(htmlSpecialChars($str, ENT_QUOTES));
  }


} // TexyCodeModule






/**
 * PARAGRAPH block module
 * ----------------------
 */
class TexySomeBlockModule extends TexyModule {


  function init() {
    $this->registerBlockPattern('blockProcess', '#^(.*)MODIFIER_H?()$#mU');
  }



  function blockProcess(&$block, &$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >


    if ($match == '') return;  // BLANK LINE
    
    if ($mContent == '') {     // MODIFIER LINE     .(title)[class]{style}<
      $block->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
      $block->modifierJustUpdated = 1;
      return;
    }

    // PARAGRAPH or DIV

    $el = &new TexyGenericBlockElement($this->texy);
    $el->modifier->copyFrom($block->modifier);
    $el->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);

    $el->children = & $this->texy->parseInline(ltrim($mContent));
    while ($block->match('#^ (\S.*)$#mUA', $matches)) {
      $el->children[] = &new TexyLineBreakElement($this->texy);
      array_splice($el->children, count($el->children), 0, $this->texy->parseInline(ltrim($matches[1])));
    }

    $el->tag = $el->hasTextualContent() ? 'p' : 'div';

    $block->children[] = & $el;
  }

} // TexySomeBlockModule


?>