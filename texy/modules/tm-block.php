<?php

/**
 * --------------------------------
 *   BLOCK - TEXY! DEFAULT MODULE
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
 * BLOCK MODULE CLASS
 */
class TexyBlockModule extends TexyModule {
  var $allowed       = true;     // generally disable / enable
  var $userFunction;             // function &myUserFunc(&$element)
  var $allowedHTML   = true;     // if false, /--html blocks are parsed as /--text block


  // constructor
  function TexyBlockModule(&$texy)
  {
    parent::TexyModule($texy);
  }


  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerBlockPattern('processBlock',   '#^/--+(code|samp|text|html|div|notexy| |$) *(\S*)MODIFIER_H?\n(.*\n)?\\\\--+()$#mUsi');
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
  function processBlock(&$blockParser, &$matches)
  {
    if (!$this->allowed) return false;
    list($match, $mType, $mLang, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
    //    [1] => code
    //    [2] => lang ?
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >
    //    [7] => .... content

    $mType = trim(strtolower($mType));
    $mLang = trim(strtolower($mLang));
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
         $blockParser->addChildren($el);
         break;


     case 'html':
     case 'notexy':
         if ($this->allowedHTML) {
           $el = &new TexyTextualElement($this->texy);
           if ($mType == 'notexy') $mContent = Texy::freezeSpaces($mContent);
           $el->setContent($mContent, true);
           $blockParser->addChildren($el);
           break;
         }


     case 'text':
         $el = &new TexyTextualElement($this->texy);
         $el->setContent( nl2br(Texy::htmlChars($mContent)), true );
         $blockParser->addChildren($el);
         break;


     default: // code | samp
         $el = &new TexyCodeBlockElement($this->texy);
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         $el->type = $mType;
         $el->lang = $mLang;

         // outdent
         $spaces = 0; while ($mContent{$spaces} == ' ') $spaces++;
         if ($spaces) $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);
         $el->setContent($mContent, false); // not html-safe content

         if ($this->userFunction)
           call_user_func_array($this->userFunction, array(&$el));

         $blockParser->addChildren($el);

    } // switch
  }



  function trustMode()
  {
    $this->allowedHTML = true;
  }



  function safeMode()
  {
    $this->allowedHTML = false;
  }


} // TexyBlockModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyTextualElement {
  var $tag = 'pre';
  var $lang;
  var $type;


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);

    $classes = $this->modifier->classes;
    $classes[] = $this->lang;
    $attr['class'] = TexyModifier::implodeClasses($classes);
  }



  function toHTML()
  {
    $this->generateTag($tag, $attr);
    if ($this->hidden) return;

    return   Texy::openingTag($tag, $attr)
           . Texy::openingTag($this->type)

           . $this->generateContent()

           . Texy::closingTag($this->type)
           . Texy::closingTag($tag);
  }

} // TexyCodeBlockElement




?>