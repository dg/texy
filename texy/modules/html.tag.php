<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
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
        $this->texy->allowed['HTML.comments'] = TRUE;
    }
    





    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerLinePattern($this, 'process', '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^"'.TEXY_HASH.']*"|=\s*\'[^\''.TEXY_HASH.']*\'|=[^>'.TEXY_HASH.']*)*)(/?)>|<!--([^'.TEXY_HASH.']*?)-->#is');
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
            if (!$this->texy->allowed['HTML.comments']) return substr($matches[5], 0, 1) === '[' ? $match : '';

            return $this->texy->hash($match, Texy::CONTENT_NONE);
        }

        $allowedTags = & $this->texy->allowedTags;
        if (!$allowedTags) return $match;   // disabled

        $tag = strtolower($mTag);
        if (!isset(TexyHtml::$valid[$tag])) $tag = $mTag;  // undo lowercase

        if (is_array($allowedTags) && !isset($allowedTags[$tag]))  // is element allowed?
            return $match;

        $el = TexyHtml::el($tag);

        if ($mEmpty === '/') $el->forceEmpty = TRUE; // or use TexyHtml autodetect 
        $isOpening = $mClosing !== '/';

        if ($el->isEmpty() && !$isOpening)  // error - can't close empty element
            return $match;


        if ($isOpening) {  // process attributes
            $allowedAttrs = is_array($allowedTags) ? $allowedTags[$tag] : NULL;
            preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
            foreach ($matchesAttr as $matchAttr) {
                $key = strtolower($matchAttr[1]);
                if (is_array($allowedAttrs) && !in_array($key, $allowedAttrs)) continue;
                $value = $matchAttr[2];
                if ($value == NULL) $value = $key;
                elseif ($value{0} === '\'' || $value{0} === '"') $value = substr($value, 1, -1);
                $el->$key = $value;
            }


            // apply allowedClasses & allowedStyles
            $modifier = new TexyModifier($this->texy);

            if (isset($el->class)) {
                $modifier->parseClasses($el->class);
                $el->class = $modifier->classes;
            }

            if (isset($el->style)) {
                $modifier->parseStyles($el->style);
                $el->style = $modifier->styles;
            }

            if (isset($el->id)) {
                if (!$this->texy->allowedClasses)
                    unset($el->id);
                elseif (is_array($this->texy->allowedClasses) && !in_array('#'.$el->id, $this->texy->allowedClasses))
                    unset($el->id);
            }


            if ($tag === 'img') {
                if (!isset($el->src)) return $match;
                $this->texy->summary['images'][] = $el->src;

            } elseif ($tag === 'a') {
                if (!isset($el->href) && !isset($el->name) && !isset($el->id)) return $match;
                if (isset($el->href)) {
                    $this->texy->summary['links'][] = $el->href;
                }
            }

        }

        //if ($this->handler)
        //    if (call_user_func_array($this->handler, array($el)) === FALSE) return '';

        return $this->texy->hash($isOpening ? $el->startTag() : $el->endTag(), $el->getContentType());
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





