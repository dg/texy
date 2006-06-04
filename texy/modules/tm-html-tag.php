<?php

/**
 * ------------------------------------
 *   HTML TAGS - TEXY! DEFAULT MODULE
 * ------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * HTML TAGS MODULE CLASS
 */
class TexyHtmlModule extends TexyModule {
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
    function TexyHtmlModule(&$texy)
    {
        parent::TexyModule($texy);

        $this->allowed = unserialize(TEXY_VALID_ELEMENTS);
    }





    /***
     * Module initialization.
     */
    function init()
    {
        $this->registerLinePattern('processTag',     '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>#is');
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
        //    [4] => /

        if (!$this->allowed) return $match;   // disabled

        static $TEXY_INLINE_ELEMENTS, $TEXY_EMPTY_ELEMENTS, $TEXY_VALID_ELEMENTS;
        if (!$TEXY_INLINE_ELEMENTS) $TEXY_INLINE_ELEMENTS = unserialize(TEXY_INLINE_ELEMENTS);
        if (!$TEXY_EMPTY_ELEMENTS) $TEXY_EMPTY_ELEMENTS = unserialize(TEXY_EMPTY_ELEMENTS);
        if (!$TEXY_VALID_ELEMENTS) $TEXY_VALID_ELEMENTS = unserialize(TEXY_VALID_ELEMENTS);

        $tag = strtolower($mTag);
        if (!isset($TEXY_VALID_ELEMENTS[$tag])) $tag = $mTag;  // undo lowercase

        $empty = ($mEmpty == '/') || isset($TEXY_EMPTY_ELEMENTS[$tag]);
        $closing = $mClosing == '/';

        if ($empty && $closing)  // error - can't close empty element
            return $match;

        if (is_array($this->allowed) && !isset($this->allowed[$tag]))  // is element allowed?
            return $match;


        $el = &new TexyHtmlTagElement($this->texy);
        $el->contentType = isset($TEXY_INLINE_ELEMENTS[$tag]) ? TEXY_CONTENT_NONE : TEXY_CONTENT_BLOCK;

        if (!$closing) {  // process attributes
            $attr = array();
            $allowedAttrs = is_array($this->allowed) ? $this->allowed[$tag] : null;
            preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
            foreach ($matchesAttr as $matchAttr) {
                $key = strtolower($matchAttr[1]);
                if (is_array($allowedAttrs) && !in_array($key, $allowedAttrs)) continue;
                $value = $matchAttr[2];
                if (!$value) $value = $key;
                elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
                $attr[$key] = $value;
            }


            // apply allowedClasses & allowedStyles
            $modifier = & $this->texy->createModifier();

            if (isset($attr['class'])) {
                $modifier->parseClasses($attr['class']);
                $attr['class'] = $modifier->classes;
            }

            if (isset($attr['style'])) {
                $modifier->parseStyles($attr['style']);
                $attr['style'] = $modifier->styles;
            }

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

            if ($empty) $attr[TEXY_EMPTY] = true;
            $el->tags[$tag] = $attr;
            $el->closing  = false;

        } else { // closing element
            $el->tags[$tag] = false;
            $el->closing  = true;
        }

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
        $this->allowed = $onlyValidTags ? unserialize(TEXY_VALID_ELEMENTS) : TEXY_ALL;
    }



    function safeMode($allowSafeTags = true)
    {
        $this->allowed = $allowSafeTags ? $this->safeTags : TEXY_NONE;
    }



} // TexyHtmlModule









/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




class TexyHtmlTagElement extends TexyDOMElement {
    var $tags;
    var $closing;
    var $contentType;



    // convert element to HTML string
    function toHTML()
    {
        if ($this->hidden) return;

        if ($this->closing)
            return Texy::closingTags($this->tags);
        else
            return Texy::openingTags($this->tags);
    }



    function addTo(&$lineElement)
    {
        $key = Texy::hashKey($this->contentType);
        $lineElement->children[$key]  = &$this;
        $lineElement->contentType = max($lineElement->contentType, $this->contentType);
        return $key;
    }


}  // TexyHtmlTagElement







?>