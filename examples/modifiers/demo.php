<?php

/**
 * ------------------------
 *   TEXY! MODIFIERS DEMO
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
 *  This demo shows how control modifiers usage
 */


$libs_path = '../../texy/';
$texy_path = $libs_path;


// include Texy!
require_once($texy_path . 'texy.php');




function doIt($styles, $classes) {
  $texy = &new Texy();
  $texy->modules['TexyFormatterModule']->baseIndent  = 1;
  $texy->allowClasses = $classes;   // true or list of allowed classes
  $texy->allowStyles  = $styles;    // true or list of allowed styles

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




echo '<h2>mode: Styles and Classes allowed (default)</h2>';
doIt(true, true);

echo '<h2>mode: Styles and Classes disabled</h2>';
doIt(false, false);

echo '<h2>mode: Selective</h2>';
doIt(
  array('color'),
  array('one', '#id')
);

?>