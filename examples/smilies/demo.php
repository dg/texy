<?php

/**
 * ----------------------
 *   TEXY! SMILIES DEMO
 * ----------------------
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
 *  This demo shows how enable smilies in Texy!
 */


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');




// include Texy!
$texyPath = '../../texy/';
require_once($texyPath . 'texy.php');



$texy = &new Texy();


// SMILIES ARE DISABLED BY DEFAULT!
// therefore module must be registered
$texy->registerModule('TexySmiliesModule');

// configure it
$texy->modules['TexySmiliesModule']->root  = 'images/';
$texy->modules['TexySmiliesModule']->class  = 'smilie';
$texy->modules['TexySmiliesModule']->icons[':oops:'] = 'redface.gif';  // user-defined smilie



// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// CSS style
echo '<style type="text/css"> .smiley { vertical-align: middle; } </style>';

// echo formated output
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>