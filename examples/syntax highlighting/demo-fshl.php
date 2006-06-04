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
 *  This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 */


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');


$texyPath = dirname(__FILE__).'/../../texy/';
$fshlPath = dirname(__FILE__).'/fshl/';


// include libs
require_once($texyPath . 'texy.php');
include_once($fshlPath . 'fshl.php');





// this is user callback function for processing blocks
//
//    /---code lang
//      ......
//      ......
//    \---
//
// $element is TexyCodeBlockElement object
//     $element->content   -  content of element
//     $element->htmlSafe  -  is content HTML safe?
//     $element->tag       -  parent HTML tag, default value is 'pre'
//     $element->type      -  type of content: code | samp | kbd | var | dfn  (or empty value)
//     $element->lang      -  language (optional)
//
//  Syntax highlighter changes $element->content and sets $element->htmlSafe to true
//
function myUserFunc(&$element) {
  $lang = strtoupper($element->lang);
  if ($lang == 'JAVASCRIPT') $lang = 'JS';
  if (!in_array(
          $lang,
          array('CPP', 'CSS', 'HTML', 'JAVA', 'PHP', 'JS', 'SQL'))
     ) return;

  $parser = new fshlParser($element->texy->utf ? 'HTML_UTF8' : 'HTML', P_TAB_INDENT);
  $element->setContent($parser->highlightString($lang, $element->content), true);
}






$texy = &new Texy();

// set user callback function for /-- code blocks
$texy->blockModule->codeHandler = 'myUserFunc';

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
echo '<style type="text/css">'. file_get_contents($fshlPath.'styles/COHEN_style.css') . '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>