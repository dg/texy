<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
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
            'a'         => array('href', 'rel', 'title', 'lang'),
            'abbr'      => array('title', 'lang'),
            'acronym'   => array('title', 'lang'),
            'b'         => array('title', 'lang'),
            'br'        => array(),
            'cite'      => array('title', 'lang'),
            'code'      => array('title', 'lang'),
            'dfn'       => array('title', 'lang'),
            'em'        => array('title', 'lang'),
            'i'         => array('title', 'lang'),
            'kbd'       => array('title', 'lang'),
            'q'         => array('cite', 'title', 'lang'),
            'samp'      => array('title', 'lang'),
            'small'     => array('title', 'lang'),
            'span'      => array('title', 'lang'),
            'strong'    => array('title', 'lang'),
            'sub'       => array('title', 'lang'),
            'sup'       => array('title', 'lang'),
            'var'       => array('title', 'lang'),
           );





    // constructor
    function TexyHtmlModule(&$texy)
    {
        parent::__construct($texy);

        $this->allowed = & $texy->allowedTags;
    }





    /**
     * Module initialization.
     */
    function init()
    {
        $this->registerLinePattern('processTag',     '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>#is');
        $this->registerLinePattern('processComment', '#<!--([^:HASH:]*)-->#Uis');
    }



    /**
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
            $attrs = array();
            $allowedAttrs = is_array($this->allowed) ? $this->allowed[$tag] : null;
            preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
            foreach ($matchesAttr as $matchAttr) {
                $key = strtolower($matchAttr[1]);
                if (is_array($allowedAttrs) && !in_array($key, $allowedAttrs)) continue;
                $value = $matchAttr[2];
                if ($value == null) $value = $key;
                elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
                $attrs[$key] = $value;
            }


            // apply allowedClasses & allowedStyles
            $modifier = & $this->texy->createModifier();

            if (isset($attrs['class'])) {
                $modifier->parseClasses($attrs['class']);
                $attrs['class'] = $modifier->classes;
            }

            if (isset($attrs['style'])) {
                $modifier->parseStyles($attrs['style']);
                $attrs['style'] = $modifier->styles;
            }

            if (isset($attrs['id'])) {
                if (!$this->texy->allowedClasses)
                    unset($attrs['id']);
                elseif (is_array($this->texy->allowedClasses) && !in_array('#'.$attrs['id'], $this->texy->allowedClasses))
                    unset($attrs['id']);
            }


            switch ($tag) {
             case 'img':
                    if (!isset($attrs['src'])) return $match;
                    $link = &$this->texy->createURL();
                    $link->set($attrs['src'], TEXY_URL_IMAGE_INLINE);
                    $this->texy->summary->images[] = $attrs['src'] = $link->URL;
                    break;

             case 'a':
                    if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) return $match;
                    if (isset($attrs['href'])) {
                        $link = &$this->texy->createURL();
                        $link->set($attrs['href']);
                        $this->texy->summary->links[] = $attrs['href'] = $link->URL;
                    }
            }

            if ($empty) $attrs[TEXY_EMPTY] = true;
            $el->tags[$tag] = $attrs;
            $el->closing  = false;

        } else { // closing element
            $el->tags[$tag] = false;
            $el->closing  = true;
        }

        return $lineParser->element->appendChild($el);
    }


    /**
     * Callback function: <!-- ... -->
     * @return string
     */
    function processComment(&$lineParser, &$matches)
    {
        // changed 16. 1. 2007
        if (!$this->allowedComments) return '';

        $el = &new TexyTextualElement($this->texy);
        $el->contentType = TEXY_CONTENT_NONE;
        $el->setContent($matches[0], TRUE);
        return $lineParser->element->appendChild($el);
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



    // convert element to HTML string
    function toHTML()
    {
        if ($this->hidden) return;

        if ($this->closing)
            return Texy::closingTags($this->tags);
        else
            return Texy::openingTags($this->tags);
    }




}  // TexyHtmlTagElement







?>