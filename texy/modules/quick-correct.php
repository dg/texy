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
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexyQuickCorrectModule extends TexyModule
{
    // @see http://www.unicode.org/cldr/data/charts/by_type/misc.delimiters.html

    // Czech Republic 
    public $doubleQuotes = array('&#8222;', '&#8220;');  // left & right double quote (&bdquo; &ldquo;)
    public $singleQuotes = array('&#8218;', '&#8216;');  // left & right single quote (&sbquo; &lsquo;)

/*
    // UK 
    public $doubleQuotes = array('&#8220;', '&#8221;');  // left & right double quote (&bdquo; &ldquo;)
    public $singleQuotes = array('&#8216;', '&#8217;');  // left & right single quote (&sbquo; &lsquo;)
*/

    private $from, $to;


    /**
     * Module initialization.
     */
    public function init()
    {                                
        $pairs = array(
          '#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'      // double ""
                                                    => $this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],

          '#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#U<UTF>'  // single ''
                                                    => $this->singleQuotes[0].'$1'.$this->singleQuotes[1],

          '#(\S|^) ?\.{3}#m'                        => '$1&#8230;',                       // ellipsis  ...
          '#(\d| )-(\d| )#'                         => "\$1&#8211;\$2",               	  // en dash    -
          '#,-#'                                    => ",&#8211;",                    	  // en dash    ,-
          '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#' => '$1&#160;$2&#160;$3',              // date 23. 1. 1978
          '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'        => '$1&#160;$2',                      // date 23. 1.
          '# --- #'                                 => " &#8212; ",                       // em dash    ---
          '# -- #'                                  => " &#8211; ",                       // en dash    --
          '# -&gt; #'                               => ' &#8594; ',                       // right arrow ->
          '# &lt;- #'                               => ' &#8592; ',                       // left arrow ->
          '# &lt;-&gt; #'                           => ' &#8596; ',                       // left right arrow <->
          '#(\d+)( ?)x\\2(\d+)\\2x\\2(\d+)#'        => '$1&#215;$3&#215;$4',              // dimension sign x
          '#(\d+)( ?)x\\2(\d+)#'                    => '$1&#215;$3',                      // dimension sign x
          '#(?<=\d)x(?= |,|.|$)#m'                  => '&#215;',                          // 10x
          '#(\S ?)\(TM\)#i'                         => '$1&#8482;',                       // trademark  (TM)
          '#(\S ?)\(R\)#i'                          => '$1&#174;',                        // registered (R)
          '#\(C\)( ?\S)#i'                          => '&#169;$1',                        // copyright  (C)
          '#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'     => '$1&#160;$2&#160;$3&#160;$4',      // (phone) number 1 123 123 123
          '#(\d{1,3}) (\d{3}) (\d{3})#'             => '$1&#160;$2&#160;$3',              // (phone) number 1 123 123
          '#(\d{1,3}) (\d{3})#'                     => '$1&#160;$2',                      // number 1 123

          '#(?<=^| |\.|,|-|\+)(\d+)([:HASHSOFT:]*) ([:HASHSOFT:]*)([:CHAR:])#m<UTF>'        // space between number and word
                                                    => '$1$2&#160;$3$4',

          '#(?<=^|[^0-9:CHAR:])([:HASHSOFT:]*)([ksvzouiKSVZOUIA])([:HASHSOFT:]*) ([:HASHSOFT:]*)([0-9:CHAR:])#m<UTF>'
                                                    => '$1$2$3&#160;$4$5',                // space between preposition and word
        );

        $this->from = $this->to = array();
        foreach ($pairs as $pattern => $replacement) {
            $this->from[] = $this->texy->translatePattern($pattern);
            $this->to[] = $replacement;
        }
    }


    public function linePostProcess($text)
    {
        if (!$this->allowed) return $text;
        return preg_replace($this->from, $this->to, $text);
    }



} // TexyQuickCorrectModule