<?php

/**
 * -------------------------------------------------
 *   AUTOMATIC REPLACEMENTS - TEXY! DEFAULT MODULE
 * -------------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexyQuickCorrectModule extends TexyModule {
    // options
    var $doubleQuotes = array('&#8222;', '&#8220;');  // left & right double quote (&bdquo; &ldquo;)
    var $singleQuotes = array('&#8218;', '&#8216;');  // left & right single quote (&sbquo; &lsquo;)
    var $dash         = '&#8211;';                    // dash (&ndash;)




    function linePostProcess(&$text)
    {
        if (!$this->allowed) return;

        static $replace;
        if (!$replace) {
            $replaceTmp = array(
              '#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'      // double ""
                                                        => $this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],

              '#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#UUTF'  // single ''
                                                        => $this->singleQuotes[0].'$1'.$this->singleQuotes[1],

              '#(\S|^) ?\.{3}#m'                        => '$1&#8230;',                       // ellipsis  ...
              '#(\d| )-(\d| )#'                         => "\$1$this->dash\$2",               // en dash    -
              '#,-#'                                    => ",$this->dash",                    // en dash    ,-
              '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#' => '$1&#160;$2&#160;$3',              // date 23. 1. 1978
              '#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'        => '$1&#160;$2',                      // date 23. 1.
              '# -- #'                                  => " $this->dash ",                   // en dash    --
              '# -&gt; #'                               => ' &#8594; ',                       // right arrow ->
              '# &lt;- #'                               => ' &#8592; ',                       // left arrow ->
              '# &lt;-&gt; #'                           => ' &#8596; ',                       // left right arrow <->
              '#(\d+) ?x ?(\d+) ?x ?(\d+)#'             => '$1&#215;$2&#215;$3',              // dimension sign x
              '#(\d+) ?x ?(\d+)#'                       => '$1&#215;$2',                      // dimension sign x
              '#(?<=\d)x(?= |,|.|$)#m'                  => '&#215;',                          // 10x
              '#(\S ?)\(TM\)#i'                         => '$1&#153;',                        // trademark  (TM)
              '#(\S ?)\(R\)#i'                          => '$1&#174;',                        // registered (R)
              '#\(C\)( ?\S)#i'                          => '&#169;$1',                        // copyright  (C)
              '#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'     => '$1&#160;$2&#160;$3&#160;$4',      // (phone) number 1 123 123 123
              '#(\d{1,3}) (\d{3}) (\d{3})#'             => '$1&#160;$2&#160;$3',              // (phone) number 1 123 123
              '#(\d{1,3}) (\d{3})#'                     => '$1&#160;$2',                      // number 1 123

              '#(?<=^| |\.|,|-|\+)(\d+)([:HASHSOFT:]*) ([:HASHSOFT:]*)([:CHAR:])#mUTF'        // space between number and word
                                                        => '$1$2&#160;$3$4',

              '#(?<=^|[^0-9:CHAR:])([:HASHSOFT:]*)([ksvzouiKSVZOUIA])([:HASHSOFT:]*) ([:HASHSOFT:]*)([0-9:CHAR:])#mUTF'
                                                        => '$1$2$3&#160;$4$5',                // space between preposition and word
            );

            $replace = array();
            foreach ($replaceTmp as $pattern => $replacement)
                $replace[ $this->texy->translatePattern($pattern) ] = $replacement;
        }

        $text = preg_replace(array_keys($replace), array_values($replace), $text);
    }



} // TexyQuickCorrectModule






?>