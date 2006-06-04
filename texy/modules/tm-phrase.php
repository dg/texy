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
 *   `....`
 *   ``....``
 */
class TexyPhraseModule extends TexyModule {
   var $allowed = array('***' => 'strong em',
                        '**'  => 'strong',
                        '*'   => 'em',
                        '++'  => 'ins',
                        '--'  => 'del',
                        '^^'  => 'sup',
                        '__'  => 'sub',
                        '"'   => 'span',
                        '~'   => 'span',
                        '~~'  => 'cite',
                        '""()'=> 'acronym',
                        '()'  => 'acronym',
                        '`'   => 'code',
                        '``'  => '',
                        );
   var $codeHandler;  // function &myUserFunc(&$element)




  /***
   * Module initialization.
   */
  function init()
  {

    // strong & em speciality *** ... ***
    if (@$this->allowed['***'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*\*(?!\*)()#U',   $this->allowed['***']);

    // **strong**
    if (@$this->allowed['**'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*(?!\*)()#U',       $this->allowed['**']);

    // *emphasis*
    if (@$this->allowed['*'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*(?!\*)()#U',           $this->allowed['*']);

    // ++inserted++
    if (@$this->allowed['++'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\+)\+\+(?!\ |\+)(.+)MODIFIER?(?<!\ |\+)\+\+(?!\+)()#U',       $this->allowed['++']);

    // --deleted--
    if (@$this->allowed['--'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\-)\-\-(?!\ |\-)(.+)MODIFIER?(?<!\ |\-)\-\-(?!\-)()#U',       $this->allowed['--']);

    // ^^superscript^^
    if (@$this->allowed['^^'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\^)\^\^(?!\ |\^)(.+)MODIFIER?(?<!\ |\^)\^\^(?!\^)()#U',       $this->allowed['^^']);

    // __subscript__
    if (@$this->allowed['__'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\_)\_\_(?!\ |\_)(.+)MODIFIER?(?<!\ |\_)\_\_(?!\_)()#U',       $this->allowed['__']);

    // "span"
    if (@$this->allowed['"'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)MODIFIER(?<!\ )\"(?!\"|\:\S)()#U',         $this->allowed['"']);
//      $this->registerLinePattern('processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+MODIFIER(?<!\ )\"(?!\"|\:\S)()#',         $this->allowed['"']);

    // ~alternative span~
    if (@$this->allowed['~'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\~)\~(?!\ )([^\~]+)MODIFIER(?<!\ )\~(?!\~|\:\S)()#U',         $this->allowed['~']);

    // ~~cite~~
    if (@$this->allowed['~~'] !== false)
      $this->registerLinePattern('processPhrase',  '#(?<!\~)\~\~(?!\ |\~)(.+)MODIFIER?(?<!\ |\~)\~\~(?!\~)()#U',       $this->allowed['~~']);

    if (@$this->allowed['""()'] !== false)
      // acronym/abbr "et al."((and others))
      $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)MODIFIER?(?<!\ )\"(?!\")\(\((.+)\)\)()#U', $this->allowed['""()']);

    if (@$this->allowed['()'] !== false)
      // acronym/abbr NATO((North Atlantic Treaty Organisation))
      $this->registerLinePattern('processPhrase',  '#(?<![:CHAR:])([:CHAR:]{2,})()()()\(\((.+)\)\)#UUTF',              $this->allowed['()']);


    // ``protected`` (experimental, dont use)
    if (@$this->allowed['``'] !== false)
      $this->registerLinePattern('processProtect', '#\`\`(\S[^:HASH:]*)(?<!\ )\`\`()#U',                               false);

    // `code`
    if (@$this->allowed['`'] !== false)
      $this->registerLinePattern('processCode',    '#\`(\S[^:HASH:]*)MODIFIER?(?<!\ )\`()#U');

    // `=samp
    $this->registerBlockPattern('processBlock',    '#^`=(none|code|kbd|samp|var|span)$#mUi');
  }




  /***
   * Callback function: **.... .(title)[class]{style}**
   * @return string
   */
  function processPhrase(&$lineParser, &$matches, $tags)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mAdditional) = $matches;
    if (!$mContent) {
      preg_match('#^(.)+(.+)'.TEXY_PATTERN_MODIFIER.'?\\1+()$#U', $match, $matches);
      list($match, $mDelim, $mContent, $mMod1, $mMod2, $mMod3, $mAdditional) = $matches;
    }
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $tags = array_reverse(explode(' ', $tags));
    $el = null;

    foreach ($tags as $tag) {
      $el = &new TexyInlineTagElement($this->texy);
      $el->tag = $tag;
      if ($tag == 'acronym') $el->modifier->title = $mAdditional;


      $mContent = $el->addTo($lineParser->element, $mContent);
    }
    if ($el)
      $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

    return $mContent;
  }





  /***
   * Callback function `=code
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mTag) = $matches;
    //    [1] => ...

    $this->tag = strtolower($mTag);
    if ($this->tag == 'none') $this->tag = '';
  }






  /***
   * Callback function: `.... .(title)[class]{style}`
   * @return string
   */
  function processCode(&$lineParser, &$matches)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $texy = &$this->texy;
    $el = &new TexyTextualElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->setContent($mContent, false);  // content isn't html safe
    $el->tag = $this->allowed['`'];

    if ($this->codeHandler)
      call_user_func_array($this->codeHandler, array(&$el));

    $el->safeContent(); // ensure that content is HTML safe

    if (isset($texy->longWordsModule))
      $texy->longWordsModule->linePostProcess($el->content);

    return $el->addTo($lineParser->element);
  }








  /***
   * User callback - PROTECT PHRASE
   * @return string
   */
  function processProtect(&$lineParser, &$matches, $isHtmlSafe = false)
  {
    list($match, $mContent) = $matches;

    $el = &new TexyTextualElement($this->texy);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->setContent( Texy::freezeSpaces($mContent), $isHtmlSafe );

    return $el->addTo($lineParser->element);
  }




} // TexyPhraseModule



?>