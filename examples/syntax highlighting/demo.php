<?php

/**
 * -----------------------------------------
 *   TEXY! THIRD PARTY SYNTAX HIGHLIGHTING
 * -----------------------------------------
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
 *  This demo shows how combine Texy! with syntax highlighter GESHI
 *       - define user callback (for @code elements)
 *       - load language, highlight and retun stylesheet + html output
 */


$libs_path = '../../texy/';
$texy_path = $libs_path;


// include Texy!
require_once($texy_path . 'texy.php');

// !!!!!!!!!!! DOWNLOAD GESHI FIRST! (http://qbnz.com/highlighter/)
$geshi_path = 'geshi/';
require_once($geshi_path.'geshi.php');





// this is user callback function for processing inlin `code` or block /---code
function myUserFunc(&$element) {
  global $geshi_path, $styleSheet;

  $geshi = new GeSHi($element->content, $element->lang, $geshi_path.'geshi/');

  if ($geshi->error) {  // GeSHi could not find the language
    $element->setContent($element->content);
    return;
  }

  // do syntax-highlighting
  $geshi->set_encoding('iso-8859-1');
  $geshi->set_header_type(GESHI_HEADER_PRE);
  $geshi->enable_classes();
  $geshi->set_overall_style('color: #000066; border: 1px solid #d0d0d0; background-color: #f0f0f0;', true);
  $geshi->set_line_style('font: normal normal 95% \'Courier New\', Courier, monospace; color: #003030;', 'font-weight: bold; color: #006060;', true);
  $geshi->set_code_style('color: #000020;', 'color: #000020;');
  $geshi->set_link_styles(GESHI_LINK, 'color: #000060;');
  $geshi->set_link_styles(GESHI_HOVER, 'background-color: #f0f000;');

  // save generated stylesheet
  $styleSheet .= $geshi->get_stylesheet();

  // change element's content
  $element->content = $geshi->parse_code();
}




$styleSheet = 'pre { padding:10px } ';
$texy = &new Texy();
$texy->modules['TexyCodeModule']->userFunction = 'myUserFunc';


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
echo '<style type="text/css">'. $styleSheet . '</style>';
echo '<title>' . $texy->headings->title . '</title>';
// echo formated output
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>