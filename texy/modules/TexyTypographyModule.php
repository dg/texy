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
 * Typography replacements module
 */
class TexyTypographyModule extends TexyModule implements ITexyLineModule
{
    protected $allow = array('typography');

    // @see http://www.unicode.org/cldr/data/charts/by_type/misc.delimiters.html

    // Czech Republic
    public $doubleQuotes = array("\xe2\x80\x9e", "\xe2\x80\x9c");  // left & right double quote
    public $singleQuotes = array("\xe2\x80\x9a", "\xe2\x80\x98");  // left & right single quote

/*
    // UK
    public $doubleQuotes = array("\xe2\x80\x9c", "\xe2\x80\x9d");  // left & right double quote
    public $singleQuotes = array("\xe2\x80\x98", "\xe2\x80\x99");  // left & right single quote
*/

    private $pattern, $replace;



    public function init()
    {
        $pairs = array(
          '#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'      // double ""
                                                    => $this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],

          '#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#Uu'  // single ''
                                                    => $this->singleQuotes[0].'$1'.$this->singleQuotes[1],

          '#(?<![.\x{2026}])\.{3,4}(?![.\x{2026}])#mu' => "\xe2\x80\xa6",                // ellipsis  ...
          '#(\d| )-(\d| )#'                         => "\$1\xe2\x80\x93\$2",             // en dash    -
          '#,-#'                                    => ",\xe2\x80\x93",                  // en dash    ,-
          '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#' => "\$1\xc2\xa0\$2\xc2\xa0\$3",      // date 23. 1. 1978
          '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'        => "\$1\xc2\xa0\$2",                 // date 23. 1.
          '# --- #'                                 => " \xe2\x80\x94 ",                 // em dash    ---
          '# -- #'                                  => " \xe2\x80\x93 ",                 // en dash    --
          '# -> #'                                  => " \xe2\x86\x92 ",                 // right arrow ->
          '# <- #'                                  => " \xe2\x86\x90 ",                 // left arrow ->
          '# <-> #'                                 => " \xe2\x86\x94 ",                 // left right arrow <->
          '#(\d+)( ?)x\\2(\d+)\\2x\\2(\d+)#'        => "\$1\xc3\x97\$3\xc3\x97\$4",      // dimension sign x
          '#(\d+)( ?)x\\2(\d+)#'                    => "\$1\xc3\x97\$3",                 // dimension sign x
          '#(?<=\d)x(?= |,|.|$)#m'                  => "\xc3\x97",                       // 10x
          '#(\S ?)\(TM\)#i'                         => "\$1\xe2\x84\xa2",                // trademark  (TM)
          '#(\S ?)\(R\)#i'                          => "\$1\xc2\xae",                    // registered (R)
          '#\(C\)( ?\S)#i'                          => "\xc2\xa9\$1",                    // copyright  (C)
          '#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'     => "\$1\xc2\xa0\$2\xc2\xa0\$3\xc2\xa0\$4", // (phone) number 1 123 123 123
          '#(\d{1,3}) (\d{3}) (\d{3})#'             => "\$1\xc2\xa0\$2\xc2\xa0\$3",      // (phone) number 1 123 123
          '#(\d{1,3}) (\d{3})#'                     => "\$1\xc2\xa0\$2",                 // number 1 123
          '#(\S{100,}) (\S+)$#'                     => "\$1\xc2\xa0\$2",                 // space before last word

          '#(?<=^| |\.|,|-|\+)(\d+)(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)(['.TEXY_CHAR.'\x{b0}])#mu' // space between number and word
                                                    => "\$1\$2\xc2\xa0\$3\$4",

          '#(?<=^|[^0-9'.TEXY_CHAR.'])(['.TEXY_MARK_N.']*)([ksvzouiKSVZOUIA])(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)([0-9'.TEXY_CHAR.'])#mu'
                                                    => "\$1\$2\$3\xc2\xa0\$4\$5",                // space between preposition and word
        );

        $this->pattern = array_keys($pairs);
	    $this->replace = array_values($pairs);
    }



    public function linePostProcess($text)
    {
        if (empty($this->texy->allowed['typography'])) return $text;

        return preg_replace($this->pattern, $this->replace, $text);
    }

} // TexyTypographyModule