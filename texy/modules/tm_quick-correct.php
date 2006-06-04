<?php

/**
 * -------------------------------------------------
 *   AUTOMATIC REPLACEMENTS - TEXY! DEFAULT MODULE
 * -------------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexyQuickCorrectModule extends TexyModule {


  function linePostProcess(&$text) {
    $HASHS = '(['.TEXY_HASH_SOFT.']*)';
    $CHAR = '['.TEXY_CHAR.']';
    $UTF  = TEXY_PATTERN_UTF;

    $replace = array(
         '#(?<!&quot;|\w)&quot;(?!\ |&quot;)(.+)(?<!\ |&quot;)&quot;(?!&quot;)()#U'
                                                              => '&bdquo;$1&ldquo;',          // double ""
         "#(?<!&\#039;|\w)&\#039;(?!\ |&\#039;)(.+)(?<!\ |&\#039;)&\#039;(?!&\#039;)()#U$UTF"
                                                              => '&sbquo;$1&lsquo;',          // single ''
         '#(\S|^) ?\.{3}#m'                                   => '$1&#8230;',                 // ellipsis  ...
         '#(\d| )-(\d| )#'                                    => '$1&ndash;$2',               // en dash    -
         '#,-#'                                               => ',&ndash;',                  // en dash    ,-
         '#(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'                   => '$1&nbsp;$2&nbsp;$3',        // date 23. 1. 1998
         '#(\d{1,2}\.) (\d{1,2}\.)#'                          => '$1&nbsp;$2',                // date 23. 1.
         '# -- #'                                             => ' &ndash; ',                 // en dash    --
         '# -&gt; #'                                          => ' &#8594; ',                 // right arrow ->
         '# &lt;- #'                                          => ' &#8592; ',                 // left arrow ->
         '# &lt;-&gt; #'                                      => ' &#8596; ',                 // left right arrow <->
         '#(\d+) ?x ?(\d+)#'                                  => '$1&#215;$2',                // dimension sign x
         '#(\S ?)\(TM\)#i'                                    => '$1&trade;',                 // trademark      (TM)
         '#(\S ?)\(R\)#i'                                     => '$1&reg;',                   // registered     (R)
         '#(\S ?)\(C\)#i'                                     => '$1&copy;',                  // copyright      (C)
         '#(\d{3}) (\d{3}) (\d{3}) (\d{3})#'                  => '$1&nbsp;$2&nbsp;$3&nbsp;$4',// phone number 123 123 123 123
         '#(\d{3}) (\d{3}) (\d{3})#'                          => '$1&nbsp;$2&nbsp;$3',        // phone number 123 123 123
         "#($CHAR)$HASHS $HASHS(\d)#m$UTF"                    => '$1$2&nbsp;$3$4',            // space before number
         "#(?<=^|[^".TEXY_CHAR."0-9])$HASHS([ksvzouiKSVZOUIA])$HASHS $HASHS($CHAR)#m$UTF"  => '$1$2$3&nbsp;$4$5',  // space after preposition
    );

    $text = preg_replace(array_keys($replace), array_values($replace), $text);
  }



} // TexyQuickCorrectModule






?>