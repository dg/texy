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


/**
 * HTML support for Texy!
 *
 */


class TexyHTML  {
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


}


?>