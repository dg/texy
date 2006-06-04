<?php

/**
 * -------------------------------
 *   CODE - TEXY! DEFAULT MODULE
 * -------------------------------
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
 * CODE MODULE CLASS
 */
class TexyBlockModule extends TexyModule {
  var $userFunction;                          // function &myUserFunc(&$element)
  var $level         = TEXY_LEVEL_TRUST_ME;   // level of benevolence


  // constructor
  function TexyBlockModule(&$texy) {
    parent::TexyModule($texy);
    $this->userFunction = array(&$this, 'userFunction');
  }


  /***
   * Module initialization.
   */
  function init() {
    $this->registerBlockPattern('processBlock',   '#^/--+(code|samp|kbd|var|dfn|notexy|div| |$) *(\S*)MODIFIER_H?\n(.*\n)?\\\\--+$()#mUsi');
  }



  /***
   * Callback function (for blocks)
   * @return object
   *
   *            /-----code html .(title)[class]{style}
   *              ....
   *              ....
   *            \----
   *
   */
  function &processBlock(&$blockParser, &$matches) {
    list($match, $mType, $mLang, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
    //    [1] => code
    //    [2] => lang ?
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >
    //    [7] => .... content

    $mType = strtolower($mType);
    $mLang = strtolower($mLang);
    if ($mType==' ') $mType = 'pre';
    $mContent = trim($mContent, "\n");

    switch ($mType) {
     case 'div':
         $el = &new TexyBlockElement($this->texy);
         $el->tag = 'div';
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         // outdent
         $spaces = 0; while ($mContent{$spaces} == ' ') $spaces++;
         if ($spaces) $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

         $el->parse($mContent);
         return $el;

     case 'notexy':
         $el = &new TexyNoTexyElement($this->texy);
         if ($this->level == TEXY_LEVEL_SAFE) $mContent = Texy::htmlChars($mContent);
         $el->content = $mLang == 'br' ? nl2br($mContent) : $mContent;
         return $el;

     default:
         $el = &new TexyCodeBlockElement($this->texy);
         $el->modifier->classes[] = $mLang;
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         $el->type = $mType;
         $el->lang = $mLang;
         // outdent
         $spaces = 0; while ($mContent{$spaces} == ' ') $spaces++;
         if ($spaces) $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);
         $el->content = $mContent;

         call_user_func_array($this->userFunction, array(&$el));

         return $el;

    } // switch
  }



  /***
   * USER Callback function (default)
   */
  function userFunction(&$element) {
    $element->setContent($element->content);
  }


} // TexyBlockModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyInlineElement {
  var $tag = 'pre';
  var $lang;
  var $type;


  function toHTML() {
    $this->generateTag($tag, $attr);
    if ($this->hidden) return;

    return   Texy::openingTag($tag, $attr)
           . Texy::openingTag($this->type)
           . $this->content
           . Texy::closingTag($this->type)
           . Texy::closingTag($tag);
  }

} // TexyCodeBlockElement






/**
 * NO-TEXY (unformatted text)
 */
class TexyNoTexyElement extends TexyDOMElement {
  var $content;


  function toHTML() {
    return $this->content;
  }

} // TexyNoTexyElement

?>