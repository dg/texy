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
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */


/**
 * HTML support for Texy!
 *
 */


class TexyHTML
{
/*
    public static $block;
    public static $inline;
    public static $empty;
    public static $meta;
    public static $accepted_attrs;


    /**
     * Like htmlSpecialChars, but preserve entities
     * @return string
     * @static
     */
    function htmlChars($s, $quotes = ENT_NOQUOTES)
    {
        return preg_replace('#'.TEXY_PATTERN_ENTITY.'#i', '&$1;', htmlSpecialChars($s, $quotes));
    }




    /**
     * Build string which represents (X)HTML opening tag
     * @param string   tag
     * @param array    associative array of attributes and values ( / mean empty tag, arrays are imploded )
     * @return string
     * @static
     */
    function openingTags($tags)
    {
        $result = '';
        foreach ((array)$tags as $tag => $attrs) {

            if ($tag == NULL) continue;

            $empty = isset($GLOBALS['TexyHTML::$empty'][$tag]) || isset($attrs[TEXY_EMPTY]);

            $attrStr = '';
            if (is_array($attrs)) {
                unset($attrs[TEXY_EMPTY]);

                foreach (array_change_key_case($attrs, CASE_LOWER) as $name => $value) {
                    if (is_array($value)) {
                        if ($name == 'style') {
                            $style = array();
                            foreach (array_change_key_case($value, CASE_LOWER) as $keyS => $valueS)
                                if ($keyS && ($valueS !== '') && ($valueS !== NULL)) $style[] = $keyS.':'.$valueS;
                            $value = implode(';', $style);
                        } else $value = implode(' ', array_unique($value));
                        if ($value == '') continue;
                    }

                    if ($value === NULL || $value === FALSE) continue;
                    $value = trim($value);
                    $attrStr .= ' '
                              . TexyHTML::htmlChars($name)
                              . '="'
                              . Texy::freezeSpaces(TexyHTML::htmlChars($value, ENT_COMPAT))   // freezed spaces will be preserved during reformating
                              . '"';
                }
            }

            $result .= '<' . $tag . $attrStr . ($empty ? ' /' : '') . '>';
        }

        return $result;
    }



    /**
     * Build string which represents (X)HTML opening tag
     * @return string
     * @static
     */
    function closingTags($tags)
    {
        $result = '';
        foreach (array_reverse((array) $tags, TRUE) as $tag => $attrs) {
            if ($tag == '') continue;
            if ( isset($GLOBALS['TexyHTML::$empty'][$tag]) || isset($attrs[TEXY_EMPTY]) ) continue;

            $result .= '</'.$tag.'>';
        }

        return $result;
    }




/*
    var $tagUsed;
    var $dontNestElements  = array('a'          => array('a'),
                                   'pre'        => array('img', 'object', 'big', 'small', 'sub', 'sup'),
                                   'button'     => array('input', 'select', 'textarea', 'label', 'button', 'form', 'fieldset', 'iframe', 'isindex'),
                                   'label'      => array('label'),
                                   'form'       => array('form'),
                                   );
*/

    // internal
    var $tagStack;
    var $autoCloseElements = array('tbody'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'colgroup'   => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'dd'         => array('dt'=>1, 'dd'=>1),
                                   'dt'         => array('dt'=>1, 'dd'=>1),
                                   'li'         => array('li'=>1),
                                   'option'     => array('option'=>1),
                                   'p'          => array('address'=>1, 'applet'=>1, 'blockquote'=>1, 'center'=>1, 'dir'=>1, 'div'=>1, 'dl'=>1, 'fieldset'=>1, 'form'=>1, 'h1'=>1, 'h2'=>1, 'h3'=>1, 'h4'=>1, 'h5'=>1, 'h6'=>1, 'hr'=>1, 'isindex'=>1, 'menu'=>1, 'object'=>1, 'ol'=>1, 'p'=>1, 'pre'=>1, 'table'=>1, 'ul'=>1),
                                   'td'         => array('th'=>1, 'td'=>1, 'tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'tfoot'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'th'         => array('th'=>1, 'td'=>1, 'tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'thead'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'tr'         => array('tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   );




    /**
     * Convert <strong><em> ... </strong> ... </em>
     *    into <strong><em> ... </em></strong><em> ... </em>
     */
    function wellForm($text)
    {
        $this->tagStack = array();
//        $this->tagUsed  = array();
        $text = preg_replace_callback('#<(/?)([a-z_:][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis', array(&$this, '_replaceWellForm'), $text);
        if ($this->tagStack) {
            $pair = end($this->tagStack);
            while ($pair !== FALSE) {
                $text .= '</'.$pair['tag'].'>';
                $pair = prev($this->tagStack);
            }
        }
        return $text;
    }



    /**
     * Callback function: <tag> | </tag>
     * @return string
     */
    function _replaceWellForm($matches)
    {
        list(, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

        if (isset($GLOBALS['TexyHTML::$empty'][$mTag]) || $mEmpty) return $mClosing ? '' : '<'.$mTag.$mAttr.' />';

        if ($mClosing) {  // closing
            $pair = end($this->tagStack);
            $s = '';
            $i = 1;
            while ($pair !== FALSE) {
                $s .= '</'.$pair['tag'].'>';
                if ($pair['tag'] == $mTag) break;
                $pair = prev($this->tagStack);
                $i++;
            }
            if ($pair === FALSE) return '';

            if (isset($GLOBALS['TexyHTML::$block'][$mTag])) {
                array_splice($this->tagStack, -$i);
                return $s;
            }

            // not work in PHP 4.4.1 due bug #35063
            unset($this->tagStack[key($this->tagStack)]);
            $pair = current($this->tagStack);
            while ($pair !== FALSE) {
                $s .= '<'.$pair['tag'].$pair['attr'].'>';
                $pair = next($this->tagStack);
            }
            return $s;

        } else {        // opening

            $s = '';

            $pair = end($this->tagStack);
            while ($pair &&
                    isset($this->autoCloseElements[$pair['tag']]) &&
                    isset($this->autoCloseElements[$pair['tag']][$mTag]) ) {

                $s .= '</'.$pair['tag'].'>';
                unset($this->tagStack[key($this->tagStack)]);

                $pair = end($this->tagStack);
            }

            $pair = array(
                'attr' => $mAttr,
                'tag' => $mTag,
            );
            $this->tagStack[] = $pair;


            $s .= '<'.$mTag.$mAttr.'>';
            return $s;
        }
    }


}


?>