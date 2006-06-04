<?php

/**
 * ------------------------------------
 *   HTML TAGS - TEXY! DEFAULT MODULE
 * ------------------------------------
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
 * HTML TAGS MODULE CLASS
 */
class TexyHTMLModule extends TexyModule {
  var $allowed;          // allowed tags (true -> all, or array, or false -> none)
                         // arrays of safe tags and attributes
  var $allowedComments = true;
  var $safeTags = array(
                     'a'         => array('href', 'rel', 'title'),
                     'abbr'      => array('title'),
                     'acronym'   => array('title'),
                     'b'         => array(),
                     'br'        => array(),
                     'cite'      => array(),
                     'code'      => array(),
                     'dfn'       => array(),
                     'em'        => array(),
                     'i'         => array(),
                     'kbd'       => array(),
                     'q'         => array('cite'),
                     'samp'      => array(),
                     'small'     => array(),
                     'span'      => array('title'),
                     'strong'    => array(),
                     'sub'       => array(),
                     'sup'       => array(),
                     'var'       => array(),
                    );





  // constructor
  function TexyHTMLModule(&$texy)
  {
    parent::TexyModule($texy);

    $this->allowed = $texy->validElements;
  }





  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerLinePattern('processTag',     '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>#is');
    $this->registerLinePattern('processComment', '#<!--([^:HASH:]*)-->#Uis');
  }



  /***
   * Callback function: <tag ...>
   * @return string
   */
  function processTag(&$lineParser, &$matches)
  {
    list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
    //    [1] => /
    //    [2] => tag
    //    [3] => attributes

    if (!$this->allowed) return $match;   // disabled

    $tag = strtolower($mTag);
    $empty = $mEmpty == '/';
    $closing = $mClosing == '/';
    $classify = Texy::classifyElement($tag);
    if ($classify & TEXY_ELEMENT_VALID) {
      $empty = $classify & TEXY_ELEMENT_EMPTY;
    } else {
      $tag = $mTag;  // undo lowercase
    }

    if ($empty && $closing)  // error - can't close empty element
      return $match;


    if (!$closing) {
      $attr = array();
      preg_match_all('#([a-z0-9-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
      foreach ($matchesAttr as $matchAttr) {
        $key = strtolower($matchAttr[1]);
        $value = $matchAttr[2];
        if (!$value) $value = $key;
        elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
        $attr[$key] = $value;
      }
    } else {
      $attr = false;
    }


    if (is_array($this->allowed)) {
      if (!isset($this->allowed[$tag]))
        return $match;

      $allowedAttrs = $this->allowed[$tag];
      if (!$closing && is_array($allowedAttrs))
        foreach ($attr as $key => $value)
          if (!in_array($key, $allowedAttrs)) unset($attr[$key]);
    }



    if (!$closing) {

      // apply allowedClasses & allowedStyles
      $modifier = & $this->texy->createModifier();

      if (isset($attr['class'])) $modifier->parseClasses($attr['class']);
      $attr['class'] = $modifier->implodeClasses( $modifier->classes );

      if (isset($attr['style'])) $modifier->parseStyles($attr['style']);
      $attr['style'] = $modifier->implodeStyles( $modifier->styles );

      if (isset($attr['id'])) {
        if (!$this->texy->allowedClasses)
          unset($attr['id']);
        elseif (is_array($this->texy->allowedClasses) && !in_array('#'.$attr['id'], $this->texy->allowedClasses))
          unset($attr['id']);
      }




      switch ($tag) {
       case 'img':
          if (!isset($attr['src'])) return $match;
          $link = &$this->texy->createURL();
          $link->set($attr['src'], TEXY_URL_IMAGE_INLINE);
          $this->texy->summary->images[] = $attr['src'] = $link->URL;
          break;

       case 'a':
          if (!isset($attr['href']) && !isset($attr['name']) && !isset($attr['id'])) return $match;
          if (isset($attr['href'])) {
            $link = &$this->texy->createURL();
            $link->set($attr['href']);
            $this->texy->summary->links[] = $attr['href'] = $link->URL;
          }
      }
    }

    $el = &new TexySingleTagElement($this->texy);
    $el->attr     = $attr;
    $el->tag      = $tag;
    $el->closing  = $closing;
    $el->empty    = $empty;
    $el->contentType = ($classify & TEXY_ELEMENT_INLINE) ? TEXY_CONTENT_NONE : TEXY_CONTENT_BLOCK;

    return $el->addTo($lineParser->element);
  }



  /***
   * Callback function: <!-- ... -->
   * @return string
   */
  function processComment(&$lineParser, &$matches)
  {
    list($match, $mContent) = $matches;
    if ($this->allowedComments) return ' ';
    else return $match;   // disabled
  }



  function trustMode($onlyValidTags = true)
  {
    $this->allowed = $onlyValidTags ? $this->texy->validElements : true;
  }



  function safeMode($allowSafeTags = true)
  {
    $this->allowed = $allowSafeTags ? $this->safeTags : false;
  }



} // TexyHTMLModule









/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




class TexySingleTagElement extends TexyDOMElement {
  var $tag;
  var $attr;
  var $closing;
  var $empty;
  var $contentType;



  // convert element to HTML string
  function toHTML()
  {
    if ($this->hidden) return;

    if ($this->empty || !$this->closing)
      return Texy::openingTag($this->tag, $this->attr, $this->empty);
    else
      return Texy::closingTag($this->tag);
  }



  function addTo(&$lineElement)
  {
    $key = Texy::hashKey($this->contentType);
    $lineElement->children[$key]  = &$this;
    $lineElement->contentType = max($lineElement->contentType, $this->contentType);
    return $key;
  }


}  // TexySingleTagElement







?>