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





class TexyFormatterModule extends TexyModule
{
    public $baseIndent  = 0;               // indent for top elements
    public $lineWrap    = 80;              // line width, doesn't include indent space
    public $indent      = TRUE;

    private $space;
    private $hashTable = array();



    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->texy->allowed['Formatter'] = TRUE;
    }


    public function postProcess($text)
    {
        if (!$this->texy->allowed['Formatter']) return $text;

        $this->space = $this->baseIndent;

        // freeze all pre, textarea, script and style elements
        $text = preg_replace_callback(
            '#<(pre|textarea|script|style)(.*)</\\1>#Uis',
            array($this, '_freeze'),
            $text
        );

        // remove \n
        $text = str_replace("\n", ' ', $text);

        // shrink multiple spaces
        $text = preg_replace('# +#', ' ', $text);

        // indent all block elements + br
        $text = preg_replace_callback(
            '# *<(/?)(' . implode(array_keys(TexyHtml::$block), '|') . '|br)(>| [^>]*>) *#i',
            array($this, 'indent'),
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
                array($this, 'wrap'),
                $text
            );

        // unfreeze pre, textarea, script and style elements
        $text = strtr($text, $this->hashTable);
        return $text;
    }





    // create new unique key for string $matches[0]
    // and saves pair (key => str) into table $this->hashTable
    private function _freeze($matches)
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
    private function indent($matches)
    {
        list($match, $mClosing, $mTag) = $matches;
        //    [1] => /  (opening or closing element)
        //    [2] => element
        //    [3] => attributes>
        $match = trim($match);
        $mTag = strtolower($mTag);

        if ($mTag === 'br')  // exception
            return "\n"
                   . str_repeat("\t", max(0, $this->space - 1))
                   . $match;

        if (isset(TexyHtml::$empty[$mTag]))
            return "\r"
                   . str_repeat("\t", $this->space)
                   . $match
                   . "\r"
                   . str_repeat("\t", $this->space);

        if ($mClosing === '/') {
            return "\x08"   // backspace
                   . $match
                   . "\n"
                   . str_repeat("\t", --$this->space);
        }

        return "\n"
               . str_repeat("\t", $this->space++)
               . $match;
    }




    /**
     * Callback function: wrap lines
     * @return string
     */
    private function wrap($matches)
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