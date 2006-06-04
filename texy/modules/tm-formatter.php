<?php

/**
 * -----------------------------------------
 *   HTML FORMATTER - TEXY! DEFAULT MODULE
 * -----------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Modules for parsing parts of text
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
 * MODULE BASE CLASS
 */
class TexyFormatterModule extends TexyModule {
  var $baseIndent  = 0;               // indent for top elements
  var $lineWrap    = 80;              // line width, doesn't include indent space
  var $indent      = true;

  // internal
  var $tagStack;
  var $tagStackAssoc;
  var $nestedElements = array('div', 'dl', 'ol', 'ul', 'blockquote', 'li', 'dd', 'span');
  var $hashTable = array();



  // constructor
  function TexyFormatterModule(&$texy)
  {
    parent::TexyModule($texy);

    // little trick - isset($array[$item]) is much faster than in_array($item, $array)
    $this->nestedElements = array_flip($this->nestedElements);
  }





  function postProcess(&$text)
  {
    $this->wellForm($text);

    if ($this->indent)
      $this->indent($text);
  }



  /***
   * Convert <strong><em> ... </strong> ... </em>
   *    into <strong><em> ... </em></strong><em> ... </em>
   */
  function wellForm(&$text)
  {
    $this->tagStack = array();
    $this->tagStackAssoc = array();
    $text = preg_replace_callback('#<(/?)([a-z][a-z0-9]*)(|\s.*|:.*)(/?)>()#Uis', array(&$this, '_replaceWellForm'), $text);
    if ($this->tagStack) {
      $pair = end($this->tagStack);
      while ($pair !== false) {
        $text .= '</'.$pair[0].'>';
        $pair = prev($this->tagStack);
      }
    }
  }



  /***
   * Callback function: <tag> | </tag>
   * @return string
   */
  function _replaceWellForm(&$matches)
  {
    list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
    //    [1] => /
    //    [2] => TAG
    //    [3] => ... (attributes)
    //    [4] => /   (empty)


    if (isset($this->texy->emptyElements[$mTag]) || $mEmpty) return $mClosing ? '' : $match;

    if ($mClosing) {  // closing
      $pair = end($this->tagStack);
      $s = '';
      $i = 1;
      while ($pair !== false) {
        if (!$pair[2]) $s .= '</'.$pair[0].'>';
        if ($pair[0] == $mTag) break;
        $pair = prev($this->tagStack);
        $i++;
      }
      if ($pair[0] <> $mTag) return '';
      if (!$pair[2]) unset($this->tagStackAssoc[$mTag]);

      if (isset($this->texy->blockElements[$mTag])) {
        array_splice($this->tagStack, -$i);
        return $s;
      }

      unset($this->tagStack[key($this->tagStack)]);
      $pair = current($this->tagStack);
      while ($pair !== false) {
        if (!$pair[2]) $s .= '<'.$pair[0].$pair[1].'>';
        $pair = next($this->tagStack);
      }
      return $s;

    } else {
                     // opening
      $hide = isset($this->tagStackAssoc[$mTag]);
      if (!isset($this->nestedElements[$mTag])) $this->tagStackAssoc[$mTag] = true;
      $this->tagStack[] = array($mTag, $mAttr, $hide);
      return $hide ? '' : $match;
    }
  }




  /***
   * Output HTML formating
   */
  function indent(&$text)
  {
    $text = preg_replace_callback('#<(pre|textarea|script|style)(.*)</\\1>#Uis', array(&$this, '_freeze'), $text);
    $this->_indent = $this->baseIndent;
    $text = str_replace(TEXY_NEWLINE, '', $text);
    $text = preg_replace('# +#', ' ', $text);
    $text = preg_replace_callback('# *<(/?)(' . implode(array_keys($this->texy->blockElements), '|') . '|br)(>| [^>]*>) *#', array(&$this, '_replaceReformat'), $text);
    $text = preg_replace('#(?<=\S)\t+#m', '', $text);
    $text = preg_replace_callback('#^(\t*)(.*)$#m', array(&$this, '_replaceWrapLines'), $text);

    $text = strtr($text, $this->hashTable); // unfreeze
  }



  // create new unique key for string $matches[0]
  // and saves pair (key => str) into table $this->hashTable
  function _freeze(&$matches)
  {
    $key = '<'.$matches[1].'>' . Texy::hashKey() . '</'.$matches[1].'>';
    $this->hashTable[$key] = $matches[0];
    return $key;
  }




  /***
   * Callback function: Insert \n + spaces into HTML code
   * @return string
   */
  function _replaceReformat(&$matches)
  {
    list($match, $mClosing, $mTag) = $matches;
    //    [1] => /  (opening or closing element)
    //    [2] => element
    //    [3] => attributes>
    $match = trim($match);
    if ($mClosing == '/') {
      return str_repeat("\t", --$this->_indent) . $match . TEXY_NEWLINE;
    } else {
      if ($mTag == 'hr') return TEXY_NEWLINE . str_repeat("\t", $this->_indent) . $match . TEXY_NEWLINE;
      if (isset($this->texy->emptyElements[$mTag])) $this->_indent--;
      return TEXY_NEWLINE . str_repeat("\t", max(0, $this->_indent++)) . $match;
    }
  }




  /***
   * Callback function: wrap lines
   * @return string
   */
  function _replaceWrapLines(&$matches)
  {
    list($match, $mSpace, $mContent) = $matches;
    return $mSpace . str_replace(TEXY_NEWLINE, TEXY_NEWLINE.$mSpace, wordwrap($mContent, $this->lineWrap));
  }



} // TexyFormatterModule




?>