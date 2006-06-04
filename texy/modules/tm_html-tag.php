<?php

/**
 * ------------------------------------
 *   HTML TAGS - TEXY! DEFAULT MODULE
 * ------------------------------------
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
 * HTML TAGS MODULE CLASS
 */
class TexyHTMLTagModule extends TexyModule {
  var $allowed       = true;                  // generally disable / enable
  var $userFunction;                 // function &myUserFunc(&$texy, &$tag, &$attr)  $attr = false --> closing tag
  var $level = TEXY_LEVEL_TRUST_ME;   // level of benevolence

  var $safeTags = array(             // array of tags and attributes accepted in TEXY_LEVEL_SAFE level
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
// and these are disabled
//                   'img'       => array('src', 'alt'),
//                   'input'     => array('type', 'name', 'value'),
//                   'label'     => array('for'),
//                   'select'    => array('name'),
//                   'button'    => array(),
//                   'big'       => array(),
//                   'textarea'  => array('name'),
//                   'address'   => array(),
//                   'blockquote'=> array('cite'),
//                   'div'       => array('class','id'),
//                   'dl'        => array(),
//                   'fieldset'  => array(),
//                   'form'      => array('action', 'method'),
//                   'h1'        => array(),
//                   'h2'        => array(),
//                   'h3'        => array(),
//                   'h4'        => array(),
//                   'h5'        => array(),
//                   'h6'        => array(),
//                   'hr'        => array(),
//                   'ol'        => array(),
//                   'p'         => array(),
//                   'pre'       => array(),
//                   'table'     => array('width'),
//                   'ul'        => array(),
//                   'dd'        => array(),
//                   'dt'        => array(),
//                   'li'        => array(),
//                   'td'        => array(),
//                   'th'        => array(),
//                   'tr'        => array(),
//                   'script'    => array(),
//                   'style'     => array(),
                    );





  /***
   * Module initialization.
   */
  function init() {
    $this->registerLinePattern('processLine', '#<(/?)([a-z0-9]+)(|\s(?:[\sa-z0-9-]|=\s*"[^"]*"|=\s*\'[^\']*\'|=[^>]*)*)/?>#is');
  }



  /***
   * Callback function: <tag ...>
   * @return string
   */
  function processLine(&$lineParser, &$matches) {
    list($match, $mClosing, $mTag, $mAttr) = $matches;
    //    [1] => /
    //    [2] => tag
    //    [3] => attributes

    if (!$this->allowed) return $match;

    if ($this->level == TEXY_LEVEL_DENIED)   // disabled
      return $match;

    $tag = strtolower($mTag);
    $closing = $mClosing == '/';
    if (!$closing) {
      $attr = array();
      preg_match_all('#([a-z0-9-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
      foreach ($matchesAttr as $matchAttr) {
        $key = strtolower($matchAttr[1]);
        $value = $matchAttr[2];
        if (!$value) $value = $key;
        elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
        $attr[$key] = $value;
      }
    } else $attr = false;


    if ($this->level == TEXY_LEVEL_SAFE) {
      // is tag allowed?
      if (!isset($this->safeTags[$tag]))
        return $match;

      if (!$closing)
        foreach ($attr as $key => $value)
          if (!in_array($key, $this->safeTags[$tag])) unset($attr[$key]);
    }


    if ($this->userFunction)  // call user function?
      call_user_func_array(
            $this->userFunction,
            array(&$this->texy, &$tag, &$attr)
      );


    if (!$tag) return $match;
    if (!$closing) {
      switch ($tag) {
       case 'img':
          if (!isset($attr['src'])) return $match;
          $url = &$this->texy->createURL();
          $url->set($attr['src'], TEXY_URL_IMAGE_INLINE);
          $this->texy->summary->images[] = $attr['src'] = $url->translate();
          break;

       case 'a':
          if (count($attr) == 0) return $match;
          if (isset($attr['href'])) {
            $url = &$this->texy->createURL();
            $url->set($attr['href']);
            $this->texy->summary->links[] = $attr['href'] = $url->URL;
          }
      }
    }

    $el = &new TexySingleTagElement($this->texy);
    if ($el === false) return $match;

    $el->attr = $attr;
    $el->tag = $tag;
    $el->closing = $closing;

    return $el->hash($lineParser->element);
  }




  /***
   * USER Callback function (default)
   * @return boolean
   */
  function userFunction(&$texy, &$tag, &$attr) {
  }



} // TexyHTMLTagModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




class TexySingleTagElement extends TexyDOMElement {
  var $tag;
  var $attr;
  var $closing;
  var $strength = TEXY_SOFT;



  // convert element to HTML string
  function toHTML() {
    if ($this->hidden) return;

    if ($this->closing)
      return Texy::closingTag($this->tag);
    else
      return Texy::openingTag($this->tag, $this->attr);
  }



  function hash(&$lineElement) {
    $key = Texy::hashKey($this->strength);
    $lineElement->children[$key]  = array(&$this, null);
    return $key;
  }


}  // TexySingleTagElement







?>