<?php

/**
 * ------------------------
 *   TEXY! HTML TAGS DEMO
 * ------------------------
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>. All rights reserved.
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


/**
 *  This demo shows how Texy! control inline html tags
 *     - three safe levels
 *     - full control over all tags and attributes
 *     - (X)HTML reformatting
 *     - well formed output
 */


$libs_path = '../../texy/';
$texy_path = $libs_path;


// include Texy!
require_once($texy_path . 'texy.php');




function doIt($level, $which = null) {
  $texy = &new Texy();
  $texy->modules['TexyHTMLTagModule']->level = $level;
  $texy->modules['TexyFormatterModule']->baseIndent  = 1;
  if ($which) $texy->modules['TexyHTMLTagModule']->safeTags = $which;

  // processing
  $text = file_get_contents('sample.texy');
  $html = $texy->process($text);  // that's all folks!

  // echo formated output
  echo $html;

  // and echo generated HTML code
  echo '<pre>';
  echo htmlSpecialChars($html);
  echo '</pre>';
  echo '<hr />';
}




echo '<h2>mode: TEXY_LEVEL_TRUST_ME</h2>';
doIt(TEXY_LEVEL_TRUST_ME);

echo '<h2>mode: TEXY_LEVEL_SAFE</h2>';
doIt(TEXY_LEVEL_SAFE);

echo '<h2>mode: TEXY_LEVEL_DENIED</h2>';
doIt(TEXY_LEVEL_DENIED);

echo '<h2>mode: CUSTOM</h2>';
doIt(TEXY_LEVEL_SAFE,
     array(            // enable only tags <a> (with attributes href, rel, title) and <strong>
         'a'         => array('href', 'rel', 'title'),
         'strong'    => array(),
     ));

?>