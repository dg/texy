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
 * MODULE BASE CLASS
 */
class TexyFormatterModule extends TexyModule {
    var $baseIndent  = 0;               // indent for top elements
    var $lineWrap    = 80;              // line width, doesn't include indent space
    var $indent      = true;
    var $wellForm    = true;            // WARNING! don't change this value

    // internal
    var $tagStack;
    var $tagUsed;
    var $dontNestElements  = array('a'          => array('a'),
                                   'pre'        => array('img', 'object', 'big', 'small', 'sub', 'sup'),
                                   'button'     => array('input', 'select', 'textarea', 'label', 'button', 'form', 'fieldset', 'iframe', 'isindex'),
                                   'label'      => array('label'),
                                   'form'       => array('form'),
                                   );
    var $autoCloseElements = array('tbody'      => array('thead', 'tbody', 'tfoot', 'colgoup'),
                                   'colgroup'   => array('thead', 'tbody', 'tfoot', 'colgoup'),
                                   'dd'         => array('dt', 'dd'),
                                   'dt'         => array('dt', 'dd'),
                                   'li'         => array('li'),
                                   'option'     => array('option'),
                                   'p'          => array('address', 'blockquote', 'div', 'dl', 'fieldset', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'legend', 'object', 'ol', 'p', 'pre', 'table', 'ul'),
                                   'td'         => array('th', 'td', 'tr', 'thead', 'tbody', 'tfoot', 'colgoup'),
                                   'tfoot'      => array('thead', 'tbody', 'tfoot', 'colgoup'),
                                   'th'         => array('th', 'td', 'tr', 'thead', 'tbody', 'tfoot', 'colgoup'),
                                   'thead'      => array('thead', 'tbody', 'tfoot', 'colgoup'),
                                   'tr'         => array('tr', 'thead', 'tbody', 'tfoot', 'colgoup'),
                                   );
    var $hashTable = array();




    // constructor
    function TexyFormatterModule(&$texy)
    {
        parent::__construct($texy);

        // little trick - isset($array[$item]) is much faster than in_array($item, $array)
        foreach ($this->autoCloseElements as $key => $value)
            $this->autoCloseElements[$key] = array_flip($value);

        $this->TEXY_EMPTY_ELEMENTS = unserialize(TEXY_EMPTY_ELEMENTS);
        $this->TEXY_BLOCK_ELEMENTS = unserialize(TEXY_BLOCK_ELEMENTS);
    }





    function postProcess(&$text)
    {
        if ($this->wellForm)
            $this->wellForm($text);

        if ($this->indent)
            $this->indent($text);
    }



    /**
     * Convert <strong><em> ... </strong> ... </em>
     *    into <strong><em> ... </em></strong><em> ... </em>
     */
    function wellForm(&$text)
    {
        $this->tagStack = array();
        $this->tagUsed  = array();
        $text = preg_replace_callback('#<(/?)([a-z_:][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis', array(&$this, '_replaceWellForm'), $text);
        if ($this->tagStack) {
            $pair = end($this->tagStack);
            while ($pair !== false) {
                $text .= '</'.$pair->tag.'>';
                $pair = prev($this->tagStack);
            }
        }
    }



    /**
     * Callback function: <tag> | </tag>
     * @return string
     */
    function _replaceWellForm(&$matches)
    {
        list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

        if (isset($this->TEXY_EMPTY_ELEMENTS[$mTag]) || $mEmpty) return $mClosing ? '' : '<'.$mTag.$mAttr.' />';

        if ($mClosing) {  // closing
            $pair = end($this->tagStack);
            $s = '';
            $i = 1;
            while ($pair !== false) {
                $s .= '</'.$pair->tag.'>';
                if ($pair->tag == $mTag) break;
                $this->tagUsed[$pair->tag]--;
                $pair = prev($this->tagStack);
                $i++;
            }
            if ($pair === false) return '';

            if (isset($this->TEXY_BLOCK_ELEMENTS[$mTag])) {
                array_splice($this->tagStack, -$i);
                return $s;
            }

            unset($this->tagStack[key($this->tagStack)]);
            $pair = current($this->tagStack);
            while ($pair !== false) {
                $s .= '<'.$pair->tag.$pair->attr.'>';
                @$this->tagUsed[$pair->tag]++;
                $pair = next($this->tagStack);
            }
            return $s;

        } else {        // opening

            $s = '';

            $pair = end($this->tagStack);
            while ($pair &&
                    isset($this->autoCloseElements[$pair->tag]) &&
                    isset($this->autoCloseElements[$pair->tag][$mTag]) ) {

                $s .= '</'.$pair->tag.'>';
                $this->tagUsed[$pair->tag]--;
                unset($this->tagStack[key($this->tagStack)]);

                $pair = end($this->tagStack);
            }

            unset($pair);
            $pair->attr = $mAttr;
            $pair->tag = $mTag;
            $this->tagStack[] = $pair;
            @$this->tagUsed[$pair->tag]++;


            $s .= '<'.$mTag.$mAttr.'>';
            return $s;
        }
    }




    /**
     * Output HTML formating
     */
    function indent(&$text)
    {
        $this->_indent = $this->baseIndent;

        // freeze all pre, textarea, script and style elements
        $text = preg_replace_callback(
                       '#<(pre|textarea|script|style)(.*)</\\1>#Uis',
                       array(&$this, '_freeze'),
                       $text
        );

        // remove \n
        $text = str_replace("\n", '', $text);

        // shrink multiple spaces
        $text = preg_replace('# +#', ' ', $text);

        // indent all block elements + br
        $text = preg_replace_callback(
                       '# *<(/?)(' . implode(array_keys($this->TEXY_BLOCK_ELEMENTS), '|') . '|br)(>| [^>]*>) *#i',
                       array(&$this, '_replaceReformat'),
                       $text
        );

        // right trim
        $text = preg_replace("#[\t ]+(\n|\r|$)#", '$1', $text); // right trim

        // join double \r to single \n
        $text = strtr($text, array("\r\r" => "\n", "\r" => "\n"));

        // "backtabulators"
        $text = strtr($text, array("\t\x08" => '', "\x08" => ''));

        // line wrap
        if ($this->lineWrap > 0)
            $text = preg_replace_callback(
                             '#^(\t*)(.*)$#m',
                             array(&$this, '_replaceWrapLines'),
                             $text
            );

        // unfreeze pre, textarea, script and style elements
        $text = strtr($text, $this->hashTable);
    }





    // create new unique key for string $matches[0]
    // and saves pair (key => str) into table $this->hashTable
    function _freeze(&$matches)
    {
        $key = '<'.$matches[1].'>' . Texy::hashKey() . '</'.$matches[1].'>';
        $this->hashTable[$key] = $matches[0];
        return $key;
    }




    /**
     * Callback function: Insert \n + spaces into HTML code
     * @return string
     */
    function _replaceReformat(&$matches)
    {
        list($match, $mClosing, $mTag) = $matches;
        //    [1] => /  (opening or closing element)
        //    [2] => element
        //    [3] => attributes>
        $match = trim($match);
        $mTag = strtolower($mTag);

        if ($mTag === 'br')  // exception
            return "\n"
                   . str_repeat("\t", max(0, $this->_indent - 1))
                   . $match;

        if (isset($this->TEXY_EMPTY_ELEMENTS[$mTag]))
            return "\r"
                   . str_repeat("\t", $this->_indent)
                   . $match
                   . "\r"
                   . str_repeat("\t", $this->_indent);

        if ($mClosing === '/') {
            return "\x08"   // backspace
                   . $match
                   . "\n"
                   . str_repeat("\t", --$this->_indent);
        }

        return "\n"
               . str_repeat("\t", $this->_indent++)
               . $match;
    }




    /**
     * Callback function: wrap lines
     * @return string
     */
    function _replaceWrapLines(&$matches)
    {
        list($match, $mSpace, $mContent) = $matches;
        return $mSpace
               . str_replace(
                      "\n",
                      "\n".$mSpace,
                      wordwrap($mContent, $this->lineWrap)
                 );
    }



} // TexyFormatterModule




?>