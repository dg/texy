<?php

/**
 * --------------
 *   TEXY! DEMO 
 * --------------
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>. All rights reserved.
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


$libs_path = '../texy/';
$texy_path = $libs_path;

// global configuration Texy!
define ('TEXY_UTF8', true);     // UTF-8 input

// include libs
require_once($texy_path . 'texy.php');



$texy = &new Texy();

// configuration
$texy->imageRoot       = 'images/'; 
$texy->linkImageRoot   = 'images/big/';
$texy->imageOnClick    = '';
$texy->imageLeftClass  = '';    
$texy->imageRightClass = '';   
$texy->format->baseIndent  = 0; 
$texy->format->indentSpace = "\t"; 

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);

// echo formated output
header("Content-type: text/html; charset=utf-8");
echo $html;

// echo HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';

?>