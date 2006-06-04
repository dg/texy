<?php

/**
 * ----------------------------------
 *   PHRASES - TEXY! DEFAULT MODULE
 * ----------------------------------
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
 * PHRASES MODULE CLASS
 *
 *   **strong**
 *   *emphasis*
 *   ***strong+emphasis***
 *   ^^superscript^^
 *   __subscript__
 *   ++inserted++
 *   --deleted--
 *   ~~cite~~
 *   "span"
 *   ~span~
 */
class TexyPhrasesModule extends TexyModule {
  var $codeTag = 'code';  // default tag for `...`


  /***
   * Module initialization.
   */
  function init() {
    $CHAR = '['.TEXY_CHAR.']';

    // code phrase ` .... `
    $this->registerLinePattern('processCode',     '#\`(\S[^'.TEXY_HASH.']*)MODIFIER?(?<!\ )\`()#U');

    // strong & em speciality *** ... ***
    $this->registerLinePattern('processPhraseStrongEm', '#(?<!\*)\*\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*\*(?!\*)()#U');

    // ++inserted++
    $this->registerLinePattern('processPhrase', '#(?<!\+)\+\+(?!\ |\+)(.+)MODIFIER?(?<!\ |\+)\+\+(?!\+)()#U', 'ins');

    // --deleted--
    $this->registerLinePattern('processPhrase', '#(?<!\-)\-\-(?!\ |\-)(.+)MODIFIER?(?<!\ |\-)\-\-(?!\-)()#U', 'del');

    // ^^superscript^^
    $this->registerLinePattern('processPhrase', '#(?<!\^)\^\^(?!\ )([^\^]+)MODIFIER?(?<!\ )\^\^(?!\^\^)()#U', 'sup');

    // __subscript__
    $this->registerLinePattern('processPhrase', '#(?<!\_)\_\_(?!\ )([^\_]+)MODIFIER?(?<!\ )\_\_(?!\_\_)()#U', 'sub');

    // "span"
    $this->registerLinePattern('processPhrase', '#(?<!\")\"(?!\ )([^\"]+)MODIFIER(?<!\ )\"(?!\")()#U', 'span');

    // ~alternative span~
    $this->registerLinePattern('processPhrase', '#(?<!\~)\~(?!\ )([^\~]+)MODIFIER(?<!\ )\~(?!\~)()#U', 'span');

    // ~~cite~~
    $this->registerLinePattern('processPhrase', '#(?<!\~)\~\~(?!\ )([^\~]+)MODIFIER?(?<!\ )\~\~(?!\~)()#U', 'cite');

    // **strong**
    $this->registerLinePattern('processPhrase', '#(?<!\*)\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*(?!\*)()#U', 'strong');

    // *emphasis*
    $this->registerLinePattern('processPhrase', '#(?<!\*)\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*(?!\*)()#U', 'em');

    // abbr "et al."((and others))
    $this->registerLinePattern('processPhrase', '#(?<!\")\"(?!\ )([^\"]+)MODIFIER?(?<!\ )\"(?!\")\(\((.+)\)\)()#U', 'abbr');

    // acronym NATO((North Atlantic Treaty Organisation))
    $this->registerLinePattern('processAbbr',  "#(?<!$CHAR)($CHAR{2,})\(\((.+)\)\)#U".TEXY_PATTERN_UTF, 'acronym');
  }



  /***
   * Callback function: **.... .(title)[class]{style}**
   * @return string
   */
  function processPhrase(&$lineParser, &$matches, $tag) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mAdditional) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $el = &new TexyInlineTagElement($this->texy);
    $el->tag = $tag;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    if ($tag == 'abbr') $el->modifier->title = $mAdditional;
    return $el->hash($lineParser->element, $mContent);
  }




  /***
   * Callback function: `.... .(title)[class]{style}`
   * @return string
   */
  function processCode(&$lineParser, &$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $texy = &$this->texy;
    $el = &new TexyInlineElement($texy);
    $el->textualContent = true;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el->setContent($mContent);
    $el->tag = $this->codeTag;

    if (isset($texy->modules['TexyLongWordsModule']))
      $texy->modules['TexyLongWordsModule']->inlinePostProcess($el->content);

    return $el->hash($lineParser->element);
  }





  /***
   * Callback function: ***.... .(title)[class]{style}***
   * @return string
   */
  function processPhraseStrongEm(&$lineParser, &$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [3] => (title)
    //    [2] => [class]
    //    [3] => {style}

    $el = &new TexyInlineTagElement($this->texy);
    $el->tag = 'strong';
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el2 = &new TexyInlineTagElement($this->texy);
    $el2->tag = 'em';

    return $el->hash(
               $lineParser->element,
               $el2->hash($lineParser->element, $mContent)
    );
  }






  /***
   * Callback function: NATO(( ... ))
   * @return string
   */
  function processAbbr(&$lineParser, &$matches) {
    list($match, $mAcronym, $mExplain) = $matches;
    //    [1] => NATO
    //    [2] => ....

    $el = &new TexyInlineTagElement($this->texy);
    $el->tag = 'acronym';
    $el->modifier->title = $mExplain;
    return $el->hash($lineParser->element, $mAcronym);
  }





  /***
   * User callback - PROTECT CODE
   * not used by Texy!
   * @return string
   */
  function protectPhrase(&$lineParser, &$matches, $dohtmlChars = true) {
    list($match) = $matches;

    $el = &new TexyInlineElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el->content = $dohtmlChars ? Texy::htmlChars($match) : $match;

    return $el->hash($lineParser->element);
  }


} // TexyPhrasesModule



?>