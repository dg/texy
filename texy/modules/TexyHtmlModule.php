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
        'html/tag' => TRUE,
        'html/comment' => TRUE,
    );


    /** @var bool   pass HTML comments to output? */
    public $passComment = FALSE;




    public function begin()
    {
        $this->texy->registerLinePattern(
            array($this, 'patternTag'),
            '#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>#is',
            'html/tag'
        );

        $this->texy->registerLinePattern(
            array($this, 'patternComment'),
            '#<!--([^'.TEXY_MARK.']*?)-->#is',
            'html/comment'
        );
    }



    /**
     * Callback for: <!-- comment -->
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternComment($parser, $matches)
    {
        list($match) = $matches;

        if (is_callable(array($this->texy->handler, 'htmlComment'))) {
            $res = $this->texy->handler->htmlComment($parser, $match);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solveComment($match);
    }




    /**
     * Callback for: <tag attr="..">
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternTag($parser, $matches)
    {
        list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => tag
        //    [3] => attributes
        //    [4] => /

        $tx = $this->texy;

        $isStart = $mEnd !== '/';
        $isEmpty = $mEmpty === '/';
        if (!$isEmpty && substr($mAttr, -1) === '/') { // uvizlo v $mAttr?
            $mAttr = substr($mAttr, 0, -1);
            $isEmpty = TRUE;
        }

        // error - can't close empty element
        if ($isEmpty && !$isStart)
            return FALSE;


        // error - end element with atttrs
        $mAttr = trim(strtr($mAttr, "\n", ' '));
        if ($mAttr && !$isStart)
            return FALSE;


        $el = TexyHtml::el($mTag);

        // end tag? we are finished
        if (!$isStart) {
            if (is_callable(array($tx->handler, 'htmlTag'))) {
                $res = $tx->handler->htmlTag($parser, $el, FALSE);
                if ($res !== Texy::PROCEED) return $res;
            }

            return $this->solveTag($el, FALSE);
        }

        // parse attributes
        $matches2 = NULL;
        preg_match_all(
            '#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',
            $mAttr,
            $matches2,
            PREG_SET_ORDER
        );

        foreach ($matches2 as $m) {
            $key = strtolower($m[1]); // strtolower protects TexyHtml's elName, userData, childNodes
            $val = $m[2];
            if ($val == NULL) $el->$key = TRUE;
            elseif ($val{0} === '\'' || $val{0} === '"') $el->$key = Texy::unescapeHtml(substr($val, 1, -1));
            else $el->$key = Texy::unescapeHtml($val);
        }

        if (is_callable(array($tx->handler, 'htmlTag'))) {
            $res = $tx->handler->htmlTag($parser, $el, TRUE, $isEmpty);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solveTag($el, TRUE, $isEmpty);
    }




    /**
     * Finish invocation
     *
     * @param TexyHtml  element
     * @param bool      is start tag?
     * @param bool      is empty?
     * @return string|FALSE
     */
    public function solveTag(TexyHtml $el, $isStart, $forceEmpty=NULL)
    {
        $tx = $this->texy;

        // tag & attibutes
        $aTags = $tx->allowedTags; // speed-up
        if (!$aTags) return FALSE;  // all tags are disabled

        if (is_array($aTags)) {
            if (!isset($aTags[$el->elName])) {
                $el->setElement(strtolower($el->elName));
                if (!isset($aTags[$el->elName])) return FALSE; // this element not allowed
            }
            $aAttrs = $aTags[$el->elName]; // allowed attrs

        } else {
            // complete UPPER convert to lower
            if ($el->elName === strtoupper($el->elName))
                $el->setElement(strtolower($el->elName));
            $aAttrs = NULL; // all attrs are allowed
        }

        // force empty
        if ($forceEmpty && $aTags === Texy::ALL) $el->_empty = TRUE;

        // end tag? we are finished
        if (!$isStart) {
            return $tx->protect($el->endTag(), $el->getContentType());
        }

        // process attributes
        if (is_array($aAttrs)) {
            $aAttrs = array_flip($aAttrs);
            $aAttrs['elName'] = $aAttrs['childNodes'] = $aAttrs['userData'] = TRUE; // hack for TexyHtml default props

            // skip disabled
            foreach ($el as $key => $foo)
                if (!isset($aAttrs[$key])) unset($el->$key);
        }

        // apply allowedClasses
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

        // apply allowedStyles
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

        if ($el->elName === 'img') {
            if (!isset($el->src)) return FALSE;

            if (!$tx->checkURL($el->src, 'i')) return FALSE;

            $tx->summary['images'][] = $el->src;

        } elseif ($el->elName === 'a') {
            if (!isset($el->href) && !isset($el->name) && !isset($el->id)) return FALSE;
            if (isset($el->href)) {
                if ($tx->linkModule->forceNoFollow && strpos($el->href, '//') !== FALSE) {
                    if (isset($el->rel)) $el->rel = (array) $el->rel;
                    $el->rel[] = 'nofollow';
                }

                if (!$tx->checkURL($el->href, 'a')) return FALSE;

                $tx->summary['links'][] = $el->href;
            }
        }

        return $tx->protect($el->startTag(), $el->getContentType());
    }




    /**
     * Finish invocation
     *
     * @param string
     * @return string
     */
    public function solveComment($content)
    {
        if ($this->passComment)
            return $this->texy->protect($content, Texy::CONTENT_MARKUP);

        return '';
    }


} // TexyHtmlModule
