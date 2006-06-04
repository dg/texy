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

// security - include texy.php, not this file
if (!defined('TEXY')) die();





class TexyFormatterModule extends TexyModule
{
    var $baseIndent  = 0;               // indent for top elements
    var $lineWrap    = 80;              // line width, doesn't include indent space
    var $indent      = TRUE;
    var $_indent;

    var $hashTable = array();





    function postProcess(&$text)
    {
        if (!$this->allowed) return;

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
                       '# *<(/?)(' . implode(array_keys($GLOBALS['TexyHTML::$block']), '|') . '|br)(>| [^>]*>) *#i',
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
    function _freeze($matches)
    {
        static $counter = 0;
        $key = '<'.$matches[1].'>'
             . "\x1A" . strtr(base_convert(++$counter, 10, 4), '0123', "\x1B\x1C\x1D\x1E") . "\x1A"
             . '</'.$matches[1].'>';
        $this->hashTable[$key] = $matches[0];
        return $key;
    }




    /**
     * Callback function: Insert \n + spaces into HTML code
     * @return string
     */
    function _replaceReformat($matches)
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

        if (isset($GLOBALS['TexyHTML::$empty'][$mTag]))
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
    function _replaceWrapLines($matches)
    {
        list(, $mSpace, $mContent) = $matches;
        return $mSpace
               . str_replace(
                      "\n",
                      "\n".$mSpace,
                      wordwrap($mContent, $this->lineWrap)
                 );
    }



} // TexyFormatterModule




?>