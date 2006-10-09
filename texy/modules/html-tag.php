<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * HTML TAGS MODULE CLASS
 */
class TexyHtmlModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $allowed;          // allowed tags (TRUE -> all, or array, or FALSE -> none)
                                                 // arrays of safe tags and attributes
    public $allowedComments = TRUE;
    public $safeTags = array(
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





    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->allowed = & $texy->allowedTags;
    }





    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerLinePattern($this, 'process', '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>|<!--([^:HASH:]*?)-->#is');
    }



    /**
     * Callback function: <tag ...>  | <!-- comment -->
     * @return string
     */
    public function process($parser, $matches)
    {
        list($match, $mClosing, $mTag, $mAttr, $mEmpty/*, $mComment*/) = $matches;
        //    [1] => /
        //    [2] => tag
        //    [3] => attributes
        //    [4] => /
        //    [5] => comment

        if ($mTag == '') { // comment
            if (!$this->allowedComments) return substr($matches[5], 0, 1) == '[' ? $match : '';

            $el = new TexyTextualElement($this->texy);
            $el->contentType = TexyDomElement::CONTENT_NONE;
            $el->setContent($match, TRUE);
            return $parser->element->appendChild($el);
        }

        $allowedTags = & $this->texy->allowedTags;
        if (!$allowedTags) return $match;   // disabled

        $tag = strtolower($mTag);
        if (!isset(TexyHtml::$valid[$tag])) $tag = $mTag;  // undo lowercase

        $empty = ($mEmpty == '/') || isset(TexyHtml::$empty[$tag]);
        $isOpening = $mClosing != '/';

        if ($empty && !$isOpening)  // error - can't close empty element
            return $match;

        if (is_array($allowedTags) && !isset($allowedTags[$tag]))  // is element allowed?
            return $match;


        $el = new TexyHtmlTagElement($this->texy);
        $el->contentType = isset(TexyHtml::$inline[$tag]) ? TexyDomElement::CONTENT_NONE : TexyDomElement::CONTENT_BLOCK;

        if ($isOpening) {  // process attributes
            $attrs = array();
            $allowedAttrs = is_array($allowedTags) ? $allowedTags[$tag] : NULL;
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
            $modifier = new TexyModifier($this->texy);

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
                $this->texy->summary['images'][] = $attrs['src'];
                break;

             case 'a':
                if (!isset($attrs['href']) && !isset($attrs['name']) && !isset($attrs['id'])) return $match;
                if (isset($attrs['href'])) {
                $this->texy->summary['links'][] = $attrs['href'];
                }
            }

            if ($empty) $attrs[TexyHtml::EMPTYTAG] = TRUE;
            $el->tags[$tag] = $attrs;
            $el->isOpening  = TRUE;

        } else { // closing element
            $el->tags[$tag] = FALSE;
            $el->isOpening  = FALSE;
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return '';

        return $parser->element->appendChild($el);
    }



    public function trustMode($onlyValidTags = TRUE)
    {
        $this->texy->allowedTags = $onlyValidTags ? TexyHtml::$valid : Texy::ALL;
    }



    public function safeMode($allowSafeTags = TRUE)
    {
        $this->texy->allowedTags = $allowSafeTags ? $this->safeTags : Texy::NONE;
    }



} // TexyHtmlModule











class TexyHtmlTagElement extends TexyDomElement
{
    public $tags;
    public $isOpening;



    // convert element to HTML string
    public function toHtml()
    {
        if ($this->isOpening)
            return TexyHtml::openingTags($this->tags);
        else
            return TexyHtml::closingTags($this->tags);
    }




}  // TexyHtmlTagElement