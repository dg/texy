<?php

/**
 * -------------------------------------------------
 *   TEXY! THIRD PARTY SYNTAX HIGHLIGHTING FOR PHP
 * -------------------------------------------------
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
 *  This demo shows how create new module for Texy!
 *       - this module accepts block <?php ... ?>
 *       - and highlight code by third-party highlighter
 */


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');


$libs_path = '../../texy/';
$texy_path = $libs_path;


// include Texy!
require_once($texy_path . 'texy.php');

// include user module
require_once('tum_phpblock.php');


// DOWNLOAD GESHI FIRST! (http://qbnz.com/highlighter/)
$geshi_path = dirname(__FILE__).'/geshi/';
include_once($geshi_path.'geshi.php');

if (!class_exists('Geshi'))
  die('DOWNLOAD <a href="http://qbnz.com/highlighter/">GESHI</a> AND UNPACK TO GESHI FOLDER FIRST!');




$texy = &new Texy();

// register my module
$texy->registerModule('TexyPHPCodeUserModule');
// make shortcut to module ($myModule)
$myModule = & $texy->modules['TexyPHPCodeUserModule'];
// configure module
$myModule->geshiPath = $geshi_path;


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo Geshi Stylesheet
echo '<style type="text/css">'. $texy->styleSheet . '</style>';
echo '<title>' . $texy->headings->title . '</title>';
// echo formated output
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>