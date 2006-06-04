<?php

/**
 * -----------------------------------
 *   TEXY! DOM ELEMENTS BASE CLASSES
 * -----------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Elements of Texy! "DOM"
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
 * Texy! ELEMENT BASE CLASS
 * ------------------------
 */
class TexyDOMElement {
  var $texy; // parent Texy! object
  var $hidden;


  // constructor
  function TexyDOMElement(&$texy)
  {
    $this->texy = & $texy;
  }


  // convert element to HTML string
  function toHTML()
  {
  }


  // for easy Texy! DOM manipulation
  function broadcast()
  {
    // build DOM->elements list
    $this->texy->DOM->elements[] = &$this;
  }


}  // TexyDOMElement








/**
 * HTML ELEMENT BASE CLASS
 * -----------------------
 *
 * This elements represents one HTML element
 *
 */
class TexyHTMLElement extends TexyDOMElement {
  var $tag;
  var $modifier;


  // constructor
  function TexyHTMLElement(&$texy) { // $parentModule = null, maybe in PHP5
    $this->texy = & $texy;
//    $this->parentModule = & $parentModule;
    $this->modifier = & $texy->createModifier();
  }



  function generateTag(&$tag, &$attr)
  {
    $tag  = $this->tag;

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $attr['class'] = TexyModifier::implodeClasses( $this->modifier->classes );

    $styles = $this->modifier->styles;
    $styles['text-align'] = $this->modifier->hAlign;
    $styles['vertical-align'] = $this->modifier->vAlign;
    $attr['style'] = TexyModifier::implodeStyles($styles);
  }



  // abstract
  function generateContent() { }


  // convert element to HTML string
  function toHTML()
  {
    $this->generateTag($tag, $attr);
    if ($this->hidden) return;

    return Texy::openingTag($tag, $attr)
           . $this->generateContent()
           . Texy::closingTag($tag);
  }



  function broadcast()
  {
    parent::broadcast();

    // build $texy->DOM->elementsById list
    if ($this->modifier->id)
      $this->texy->DOM->elementsById[$this->modifier->id] = &$this;

    // build $texy->DOM->elementsByClass list
    if ($this->modifier->classes)
      foreach ($this->modifier->classes as $class)
        $this->texy->DOM->elementsByClass[$class][] = &$this;
  }


}  // TexyHTMLElement











/**
 * BLOCK ELEMENT BASE CLASS
 * ------------------------
 *
 * This element represent array of other blocks (TexyHTMLElement)
 *
 */
class TexyBlockElement extends TexyHTMLElement {
  var $children = array(); // of TexyHTMLElement




  function generateContent()
  {
    $html = '';
    foreach (array_keys($this->children) as $key)
      $html .= $this->children[$key]->toHTML();

    return $html;
  }




  /***
   * Parse $text as BLOCK and create array children (array of Texy DOM elements)
   ***/
  function parse($text)
  {
    $blockParser = &new TexyBlockParser($this);
    $blockParser->parse($text);
  }



  function broadcast()
  {
    parent::broadcast();

    // apply to all children
    foreach (array_keys($this->children) as $key)
      $this->children[$key]->broadcast();
  }

}  // TexyBlockElement










/**
 * LINE OF TEXT
 * ------------
 *
 * This element represent one line of text.
 * Text represents $content and $children is array of TexyInlineTagElement
 *
 */
class TexyTextualElement extends TexyHTMLElement {
  var $children    = array();      // of TexyTextualElement

  var $contentType = TEXY_CONTENT_NONE;
  var $content;                    // string
  var $htmlSafe    = false;        // is content HTML-safe?




  function setContent($text, $isHtmlSafe = false)
  {
    $this->content = $text;
    $this->htmlSafe = $isHtmlSafe;
  }



  function safeContent($onlyReturn = false)
  {
    $safeContent = $this->htmlSafe ? $this->content : htmlSpecialChars($this->content, ENT_QUOTES);

    if ($onlyReturn) return $safeContent;
    else {
      $this->htmlSafe = true;
      return $this->content = $safeContent;
    }
  }




  function generateContent()
  {
    $content = $this->safeContent(true);

    if ($this->children) {
      $table = array();
      foreach (array_keys($this->children) as $key)
        $table[$key] = $this->children[$key]->toHTML( Texy::isHashOpening($key) );

      return strtr($content, $table);
    }

    return $content;
  }



  /***
   * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
   ***/
  function parse($text)
  {
    $lineParser = &new TexyLineParser($this);
    $lineParser->parse($text);
  }




  function toText()
  {
    return preg_replace('#['.TEXY_HASH.']+#', '', $this->content);
  }



  function broadcast()
  {
    parent::broadcast();

    // apply to all children
    foreach (array_keys($this->children) as $key)
      $this->children[$key]->broadcast();
  }




  function addTo(&$ownerElement)
  {
    $key = Texy::hashKey($this->contentType);
    $ownerElement->children[$key]  = &$this;
    $ownerElement->contentType = max($ownerElement->contentType, $this->contentType);
    return $key;
  }

}  // TexyTextualElement







/**
 * INLINE TAG ELEMENT BASE CLASS
 * -----------------------------
 *
 * Represent HTML tags (elements without content)
 * Used as children of TexyTextualElement
 *
 */
class TexyInlineTagElement extends TexyHTMLElement {
  var $contentType = TEXY_CONTENT_NONE;
  var $_closingTag;



  // convert element to HTML string
  function toHTML($opening)
  {
    if ($opening) {
      $this->generateTag($tag, $attr);
      if ($this->hidden) return;
      $this->_closingTag = Texy::closingTag($tag);
      return Texy::openingTag($tag, $attr);

    } else {
      return $this->_closingTag;
    }
  }



  function addTo(&$ownerElement, $elementContent = null)
  {
    $keyOpen  = Texy::hashKey($this->contentType, true);
    $keyClose = Texy::hashKey($this->contentType, false);

    $ownerElement->children[$keyOpen]  = &$this;
    $ownerElement->children[$keyClose] = &$this;
    return $keyOpen . $elementContent . $keyClose;
  }




} // TexyInlineTagElement

















/**
 * Texy! DOM
 * ---------
 */
class TexyDOM extends TexyBlockElement {
  var  $elements;
  var  $elementsById;
  var  $elementsByClass;


  /***
   * Convert Texy! document into DOM structure
   * Before converting it normalize text and call all pre-processing modules
   ***/
  function parse($text)
  {
      ///////////   REMOVE SPECIAL CHARS, NORMALIZE LINES
    $text = Texy::wash($text);

      ///////////   STANDARDIZE LINE ENDINGS TO UNIX-LIKE  (DOS, MAC)
    $text = str_replace("\r\n", TEXY_NEWLINE, $text); // DOS
    $text = str_replace("\r", TEXY_NEWLINE, $text); // Mac

      ///////////   REPLACE TABS WITH SPACES
    $tabWidth = $this->texy->tabWidth;
    while (strpos($text, "\t") !== false)
      $text = preg_replace_callback('#^(.*)\t#mU',
                 create_function('&$matches', "return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),
                 $text);

      ///////////   REMOVE TEXY! COMMENTS
    $commentChars = $this->texy->utf ? "\xC2\xA7" : "\xA7";
    $text = preg_replace('#'.$commentChars.'{2,}(?!'.$commentChars.').*('.$commentChars.'{2,}|$)(?!'.$commentChars.')#mU', '', $text);

      ///////////   RIGHT TRIM
    $text = preg_replace("#[\t ]+(\n|$)#", TEXY_NEWLINE, $text); // right trim


      ///////////   PRE-PROCESSING
    foreach ($this->texy->modules as $name => $moduleX)
      $this->texy->modules->$name->preProcess($text);

      ///////////   PROCESS
    parent::parse($text);
  }





  /***
   * Convert DOM structure to (X)HTML code
   * and call all post-processing modules
   * @return string
   ***/
  function toHTML()
  {
    $text = parent::toHTML();

      ///////////   POST-PROCESS
    foreach ($this->texy->modules as $name => $moduleX)
      $this->texy->modules->$name->postProcess($text);

      ///////////   UNFREEZE SPACES
    $text = Texy::unfreezeSpaces($text);

      // THIS (C) NOTICE SHOULD REMAIN!
    static $messageShowed = false;
    if (!$messageShowed) {
      $text .= "\n<!-- generated by Texy! -->";
      $messageShowed = true;
    }

    return $text;
  }




  /***
   * Build list for easy access to DOM structure
   ***/
  function buildLists()
  {
    $this->elements = array();
    $this->elementsById = array();
    $this->elementsByClass = array();
    $this->broadcast();
  }



}  // TexyDOM












/**
 * Texy! DOM for single line
 * -------------------------
 */
class TexyDOMLine extends TexyTextualElement {
  var  $elements;
  var  $elementsById;
  var  $elementsByClass;


  /***
   * Convert Texy! single line into DOM structure
   ***/
  function parse($text)
  {
      ///////////   REMOVE SPECIAL CHARS AND LINE ENDINGS
    $text = Texy::wash($text);
    $text = rtrim(strtr($text, array("\n" => ' ', "\r" => '')));

      ///////////   PROCESS
    parent::parse($text);
  }





  /***
   * Convert DOM structure to (X)HTML code
   * @return string
   ***/
  function toHTML()
  {
    $text = parent::toHTML();
    $text = Texy::unfreezeSpaces($text);
    return $text;
  }




  /***
   * Build list for easy access to DOM structure
   ***/
  function buildLists()
  {
    $this->elements = array();
    $this->elementsById = array();
    $this->elementsByClass = array();
    $this->broadcast();
  }



} // TexyDOMLine

?>