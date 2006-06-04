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


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');




// include Texy!
$texyPath = '../../texy/';
require_once($texyPath . 'texy.php');



$texy = &new Texy();
$texy->formatterModule->baseIndent  = 1;



function doIt() {
  global $texy;

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
$texy->allowedClasses = true;
$texy->allowedStyles  = true;
doIt();

echo '<h2>mode: Styles and Classes disabled</h2>';
$texy->allowedClasses = false;
$texy->allowedStyles  = false;
doIt();

echo '<h2>mode: Custom</h2>';
$texy->allowedClasses = array('one', '#id');
$texy->allowedStyles  = array('color');
doIt();

?>