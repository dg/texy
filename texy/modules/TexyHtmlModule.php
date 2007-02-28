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
 * Html tags module
 */
class TexyHtmlModule extends TexyModule
{
    protected $default = array(
        'html' => TRUE,
        'htmlTag' => TRUE,
        'htmlComment' => FALSE,
    );


    public function init()
    {
        $this->texy->registerLinePattern(
            array($this, 'process'),
            '#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is',
            'html'
        );
    }



    /**
     * Callback function: <tag ...>  | <!-- comment -->
     * @return string
     */
    public function process($parser, $matches)
    {
        $matches[] = NULL;
        list($match, $mClosing, $mTag, $mAttr, $mEmpty, $mComment) = $matches;
        //    [1] => /
        //    [2] => tag
        //    [3] => attributes
        //    [4] => /
        //    [5] => comment

        $tx = $this->texy;

        if ($mTag == '') { // html comment
            if (empty($tx->allowed['htmlComment']))
                return substr($matches[5], 0, 1) === '[' ? $match : '';

            return $tx->mark($match, Texy::CONTENT_NONE);
        }

        if (empty($tx->allowed['htmlTag'])) return FALSE;

        $tag = strtolower($mTag);
        // test for validity - not good!!
        if (!isset(Texy::$blockTags[$tag]) && !isset(Texy::$inlineTags[$tag])) $tag = $mTag;  // undo lowercase

        // tag & attibutes
        $aTags = $tx->allowedTags; // speed-up
        if (!$aTags) return FALSE;  // all tags are disabled
        if (is_array($aTags)) {
            if (!isset($aTags[$tag])) return FALSE; // this element not allowed
            $aAttrs = $aTags[$tag]; // allowed attrs
        } else {
            $aAttrs = NULL; // all attrs are allowed
        }

        $isEmpty = $mEmpty === '/';
        if (!$isEmpty && substr($mAttr, -1) === '/') {
            $mAttr = substr($mAttr, 0, -1);
            $isEmpty = TRUE;
        }
        $isOpening = $mClosing !== '/';

        if ($isEmpty && !$isOpening)  // error - can't close empty element
            return FALSE;

        $el = TexyHtml::el($tag);
        if ($aTags === Texy::ALL && $isEmpty) $el->_empty = TRUE; // force empty

        if (!$isOpening) // closing tag? we are finished
            return $tx->mark($el->endTag(), $el->getContentType());

        // process attributes
        if (is_array($aAttrs)) $aAttrs = array_flip($aAttrs);
        else $aAttrs = NULL;

        preg_match_all(
            '#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',
            $mAttr,
            $matches2,
            PREG_SET_ORDER
        );

        foreach ($matches2 as $m) {
            $key = strtolower($m[1]);
            if ($aAttrs !== NULL && !isset($aAttrs[$key])) continue;

            $val = $m[2];
            if ($val == NULL) $el->$key = TRUE;
            elseif ($val{0} === '\'' || $val{0} === '"') $el->$key = Texy::decode(substr($val, 1, -1));
            else $el->$key = Texy::decode($val);
        }


        // apply allowedClasses & allowedStyles
        $modifier = new TexyModifier;

        if (isset($el->class)) {
            $tmp = $tx->_classes; // speed-up
            if (is_array($tmp)) {
                $el->class = explode(' ', $el->class);
                foreach ($el->class as $key => $val)
                    if (!isset($tmp[$val])) unset($el->class[$key]); // id & class are case-sensitive in XHTML

                if (!isset($tmp['#' . $el->id])) $el->id = NULL;
            } elseif ($tmp !== Texy::ALL) {
                $el->class = $el->id = NULL;
            }
        }

        if (isset($el->style)) {
            $tmp = $tx->_styles;  // speed-up
            if (is_array($tmp)) {
                $styles = explode(';', $el->style);
                $el->style = NULL;
                foreach ($styles as $value) {
                    $pair = explode(':', $value, 2);
                    $prop = trim($pair[0]);
                    if (isset($pair[1]) && isset($tmp[strtolower($prop)])) // CSS is case-insensitive
                        $el->style[$prop] = $pair[1];
                }
            } elseif ($tmp !== Texy::ALL) {
                $el->style = NULL;
            }
        }

        if ($tag === 'img') {
            if (!isset($el->src)) return FALSE;
            $tx->summary['images'][] = $el->src;

        } elseif ($tag === 'a') {
            if (!isset($el->href) && !isset($el->name) && !isset($el->id)) return FALSE;
            if (isset($el->href)) {
                $tx->summary['links'][] = $el->href;
            }
        }

        return $tx->mark($el->startTag(), $el->getContentType());
    }

} // TexyHtmlModule
