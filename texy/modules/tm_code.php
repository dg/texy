<?php

/**
 * ----------------------------------
 *   CODE - TEXY! DEFAULT MODULE
 * ----------------------------------
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
 * CODE PHRASE MODULE CLASS
 *
 *   `....` or ``....``
 */
class TexyCodeModule extends TexyModule {
  var $allowed = true;          // generally disable / enable
  var $userFunction;            // function &myUserFunc(&$element)
  var $tag = 'code';            // default tag for `...`


  /***
   * Module initialization.
   */
  function init() {
    $this->registerLinePattern('processProtect',  '#\`\`(\S[^'.TEXY_HASH.']*)(?<!\ )\`\`()#U', false);
    $this->registerLinePattern('processCode',     '#\`(\S[^'.TEXY_HASH.']*)MODIFIER?(?<!\ )\`()#U');
    $this->registerBlockPattern('processBlock',   '#^`=(none|code|kbd|samp|var|span)$#mUi');
  }



  /***
   * Callback function `=code
   */
  function processBlock(&$blockParser, &$matches) {
    list($match, $mTag) = $matches;
    //    [1] => ...

    $this->tag = strtolower($mTag);
    if ($this->tag == 'none') $this->tag = '';
  }






  /***
   * Callback function: `.... .(title)[class]{style}`
   * @return string
   */
  function processCode(&$lineParser, &$matches) {
    if (!$this->allowed) return '';
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $texy = &$this->texy;
    $el = &new TexyTextualElement($texy);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el->setContent($mContent, false);  // content isn't html safe
    $el->tag = $this->tag;

    if ($this->userFunction)
      call_user_func_array($this->userFunction, array(&$el));

    $el->safeContent(); // ensure that content is HTML safe

    if (isset($texy->modules['TexyLongWordsModule']))
      $texy->modules['TexyLongWordsModule']->linePostProcess($el->content);

    return $el->addTo($lineParser->element);
  }








  /***
   * User callback - PROTECT PHRASE
   * @return string
   */
  function processProtect(&$lineParser, &$matches, $isHtmlSafe = false) {
    list($match, $mContent) = $matches;

    $el = &new TexyTextualElement($this->texy);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->setContent($mContent, $isHtmlSafe);

    return $el->addTo($lineParser->element);
  }



} // TexyCodeModule



?>