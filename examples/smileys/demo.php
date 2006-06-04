<?php

/**
 * ----------------------
 *   TEXY! SMILEYS DEMO
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
 *  This demo shows how enable 'smileys' in Texy!
 */


$libs_path = '../../texy/';
$texy_path = $libs_path;


// include Texy!
require_once($texy_path . 'texy.php');


$texy = &new Texy();



// SMILEYS ARE DISABLED BY DEFAULT!
// therefore module must be registered
$texy->registerModule('TexySmileysModule');

// configure it
$texy->modules['TexySmileysModule']->iconsRoot  = 'images/';
$texy->modules['TexySmileysModule']->class  = 'smiley';




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