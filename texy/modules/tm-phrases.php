<?php

/**
 * ----------------------------------
 *   PHRASES - TEXY! DEFAULT MODULE
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


  /***
   * Module initialization.
   */
  function init()
  {
    $CHAR = '['.TEXY_CHAR.']';

    // strong & em speciality *** ... ***
    $this->registerLinePattern('processPhraseStrongEm', '#(?<!\*)\*\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*\*(?!\*)()#U');

    // ++inserted++
    $this->registerLinePattern('processPhrase', '#(?<!\+)\+\+(?!\ |\+)(.+)MODIFIER?(?<!\ |\+)\+\+(?!\+)()#U', 'ins');

    // --deleted--
    $this->registerLinePattern('processPhrase', '#(?<!\-)\-\-(?!\ |\-)(.+)MODIFIER?(?<!\ |\-)\-\-(?!\-)()#U', 'del');

    // ^^superscript^^
    $this->registerLinePattern('processPhrase', '#(?<!\^)\^\^(?!\ )([^\^]+)MODIFIER?(?<!\ )\^\^(?!\^)()#U', 'sup');

    // __subscript__
    $this->registerLinePattern('processPhrase', '#(?<!\_)\_\_(?!\ )([^\_]+)MODIFIER?(?<!\ )\_\_(?!\_)()#U', 'sub');

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

    // acronym/abbr "et al."((and others))
    $this->registerLinePattern('processPhrase', '#(?<!\")\"(?!\ )([^\"]+)MODIFIER?(?<!\ )\"(?!\")\(\((.+)\)\)()#U', 'acronym');

    // acronym/abbr NATO((North Atlantic Treaty Organisation))
    $this->registerLinePattern('processAcronym',  "#(?<!$CHAR)($CHAR{2,})\(\((.+)\)\)#U".TEXY_PATTERN_UTF, 'acronym');
  }



  /***
   * Callback function: **.... .(title)[class]{style}**
   * @return string
   */
  function processPhrase(&$lineParser, &$matches, $tag)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mAdditional) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $el = &new TexyInlineTagElement($this->texy);
    $el->tag = $tag;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    if ($tag == 'acronym') $el->modifier->title = $mAdditional;
    return $el->addTo($lineParser->element, $mContent);
  }






  /***
   * Callback function: ***.... .(title)[class]{style}***
   * @return string
   */
  function processPhraseStrongEm(&$lineParser, &$matches)
  {
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

    return $el->addTo(
               $lineParser->element,
               $el2->addTo($lineParser->element, $mContent)
    );
  }






  /***
   * Callback function: NATO(( ... ))
   * @return string
   */
  function processAcronym(&$lineParser, &$matches)
  {
    list($match, $mAcronym, $mExplain) = $matches;
    //    [1] => NATO
    //    [2] => ....

    $el = &new TexyInlineTagElement($this->texy);
    $el->tag = 'acronym';
    $el->modifier->title = $mExplain;
    return $el->addTo($lineParser->element, $mAcronym);
  }



} // TexyPhrasesModule



?>