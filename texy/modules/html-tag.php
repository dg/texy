<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * HTML TAGS MODULE CLASS
 */
class TexyHtmlModule extends TexyModule {
    var $allowed;          // allowed tags (TRUE -> all, or array, or FALSE -> none)
                                                 // arrays of safe tags and attributes
    var $allowedComments = TRUE;
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





    function __construct(&$texy)
    {
        parent::__construct($texy);

        $this->allowed = & $texy->allowedTags;
    }





    /**
     * Module initialization.
     */
    function init()
    {
        $this->texy->registerLinePattern($this, 'processTag',     '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>#is');
        $this->texy->registerLinePattern($this, 'processComment', '#<!--([^:HASH:]*)-->#Uis');
        //$this->texy->registerLinePattern($this, 'processEntity',    '#&([a-z]+|\\#x[0-9a-f]+|\\#[0-9]+);#i');
    }



    /**
     * Callback function: <tag ...>
     * @return string
     */
    function processTag(&$parser, $matches)
    {
        list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => tag
        //    [3] => attributes
        //    [4] => /

        $allowedTags = & $this->texy->allowedTags;
        if (!$allowedTags) return $match;   // disabled

        $tag = strtolower($mTag);
        if (!isset($GLOBALS['TexyHTML::$valid'][$tag])) $tag = $mTag;  // undo lowercase

        $empty = ($mEmpty == '/') || isset($GLOBALS['TexyHTML::$empty'][$tag]);
        $closing = $mClosing == '/';

        if ($empty && $closing)  // error - can't close empty element
            return $match;

        if (is_array($this->allowed) && !isset($this->allowed[$tag]))  // is element allowed?
            return $match;


        $el = &new TexyHtmlTagElement($this->texy);
        $el->contentType = isset($GLOBALS['TexyHTML::$inline'][$tag]) ? TEXY_CONTENT_NONE : TEXY_CONTENT_BLOCK;

        if (!$closing) {  // process attributes
            $attrs = array();
            $allowedAttrs = is_array($this->allowed) ? $this->allowed[$tag] : NULL;
            preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
            foreach ($matchesAttr as $matchAttr) {
                $key = strtolower($matchAttr[1]);
                if (is_array($allowedAttrs) && !in_array($key, $allowedAttrs)) continue;
                $value = $matchAttr[2];
                if ($value == NULL) $value = $key;
                elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
                $attrs[$key] = $value;
            }


            // apply allowedClasses & allowedStyles
            $modifier = &new TexyModifier($this->texy);

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
                $this->texy->summary->images[] = $attrs['src'];
                    break;
                /*
                $link = &new TexyURL($this->texy);
                $link->set($attrs['src']);
                $this->texy->summary->images[] = $attrs['src'] = $link->asURL();
                */

             case 'a':
                    if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) return $match;
                    if (isset($attrs['href'])) {
                    $this->texy->summary->links[] = $attrs['href'];
                    /*
                    $link = new TexyURL($this->texy);
                        $link->set($attrs['href']);
                    $this->texy->summary->links[] = $attrs['href'] = $link->asURL();
                    */
                    }
            }

            if ($empty) $attrs[TEXY_EMPTY] = TRUE;
            $el->tags[$tag] = $attrs;
            $el->closing  = FALSE;

        } else { // closing element
            $el->tags[$tag] = FALSE;
            $el->closing  = TRUE;
        }

        return $parser->element->appendChild($el);
    }


    /**
     * Callback function: <!-- ... -->
     * @return string
     */
    function processComment(&$parser, $matches)
    {
        list($match, $mContent) = $matches;
        if ($this->allowedComments) return ' ';
        else return $match;   // disabled
    }



    /**
     * Callback function: &amp;  |  &#039;  |  &#x1A;
     * @return string
     */
/*
    function processEntity($parser, $matches)
    {
        list($mEntity) = $matches;
        return html_entity_decode($mEntity, ENT_QUOTES, 'UTF-8');
    }
*/


    function trustMode($onlyValidTags = TRUE)
    {
        $this->texy->allowedTags = $onlyValidTags ? $GLOBALS['TexyHTML::$valid'] : TEXY_ALL;
    }



    function safeMode($allowSafeTags = TRUE)
    {
        $this->texy->allowedTags = $allowSafeTags ? $this->safeTags : TEXY_NONE;
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
            return TexyHTML::closingTags($this->tags);
        else
            return TexyHTML::openingTags($this->tags);
    }




}  // TexyHtmlTagElement







?>