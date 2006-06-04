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
  // options
  var $doubleQuotes = array('&bdquo;', '&ldquo;');
  var $singleQuotes = array('&sbquo;', '&lsquo;');
  var $dash         = '&ndash;';




  function linePostProcess(&$text)
  {
    if (!$this->allowed) return;

    $HASHS   = '(['.TEXY_HASH_SOFT.']*)';
    $CHAR    = TEXY_CHAR;
    $UTF     = TEXY_PATTERN_UTF;
    $DASH    = $this->dash;

    $replace = array(
         '#(?<!&quot;|\w)&quot;(?!\ |&quot;)(.+)(?<!\ |&quot;)&quot;(?!&quot;)()#U'
                              => $this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],          // double ""
         "#(?<!&\#039;|\w)&\#039;(?!\ |&\#039;)(.+)(?<!\ |&\#039;)&\#039;(?!&\#039;)()#U$UTF"
                              => $this->singleQuotes[0].'$1'.$this->singleQuotes[1],          // single ''
         '#(\S|^) ?\.{3}#m'                                   => '$1&#8230;',                 // ellipsis  ...
         '#(\d| )-(\d| )#'                                    => "\$1$DASH\$2",               // en dash    -
         '#,-#'                                               => ",$DASH",                    // en dash    ,-
         '#(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'                   => '$1&nbsp;$2&nbsp;$3',        // date 23. 1. 1998
         '#(\d{1,2}\.) (\d{1,2}\.)#'                          => '$1&nbsp;$2',                // date 23. 1.
         '# -- #'                                             => " $DASH ",                   // en dash    --
         '# -&gt; #'                                          => ' &#8594; ',                 // right arrow ->
         '# &lt;- #'                                          => ' &#8592; ',                 // left arrow ->
         '# &lt;-&gt; #'                                      => ' &#8596; ',                 // left right arrow <->
         '#(\d+) ?x ?(\d+)#'                                  => '$1&#215;$2',                // dimension sign x
         '#(\S ?)\(TM\)#i'                                    => '$1&trade;',                 // trademark      (TM)
         '#(\S ?)\(R\)#i'                                     => '$1&reg;',                   // registered     (R)
         '#(\S ?)\(C\)#i'                                     => '$1&copy;',                  // copyright      (C)
         '#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'                => '$1&nbsp;$2&nbsp;$3&nbsp;$4',// (phone) number 1 123 123 123
         '#(\d{1,3}) (\d{3}) (\d{3})#'                        => '$1&nbsp;$2&nbsp;$3',        // (phone) number 1 123 123
         '#(\d{1,3}) (\d{3})#'                                => '$1&nbsp;$2',                // number 1 123
         "#(?<=^| )(\d+)$HASHS $HASHS([$CHAR])#m$UTF"         => '$1$2&nbsp;$3$4',            // space after number
         "#(?<=^|[^0-9$CHAR])$HASHS([ksvzouiKSVZOUIA])$HASHS $HASHS([0-9$CHAR])#m$UTF"  => '$1$2$3&nbsp;$4$5',  // space after preposition
    );

    $text = preg_replace(array_keys($replace), array_values($replace), $text);
  }



} // TexyQuickCorrectModule






?>