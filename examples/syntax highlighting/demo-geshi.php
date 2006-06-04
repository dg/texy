<?php

/**
 * TEXY! THIRD PARTY SYNTAX HIGHLIGHTING
 * --------------------------------------
 *
 * This demo shows how combine Texy! with syntax highlighter GESHI
 *       - define user callback (for /--code elements)
 *       - load language, highlight and return stylesheet + html output
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 */


$texyPath = dirname(__FILE__).'/../../texy/';
$geshiPath = dirname(__FILE__).'/geshi/';


// include libs
require_once ($texyPath . 'texy.php');
if (is_file($geshiPath . 'geshi.php')) include_once ($geshiPath . 'geshi.php');


if (!class_exists('Geshi'))
    die('DOWNLOAD <a href="http://qbnz.com/highlighter/">GESHI</a> AND UNPACK TO GESHI FOLDER FIRST!');



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
//  Syntax highlighter changes $element->content and sets $element->htmlSafe to TRUE
//
function myUserFunc(&$element) {
    global $geshiPath;

    if ($element->lang == 'html') $element->lang = 'html4strict';
    $geshi = new GeSHi($element->content, $element->lang, $geshiPath.'geshi/');

    if ($geshi->error)   // GeSHi could not find the language, nothing to do
        return;

    // do syntax-highlighting
    $geshi->set_encoding($element->texy->utf ? 'UTF-8' : 'ISO-8859-1');
    $geshi->set_header_type(GESHI_HEADER_PRE);
    $geshi->enable_classes();
    $geshi->set_overall_style('color: #000066; border: 1px solid #d0d0d0; background-color: #f0f0f0;', TRUE);
    $geshi->set_line_style('font: normal normal 95% \'Courier New\', Courier, monospace; color: #003030;', 'font-weight: bold; color: #006060;', TRUE);
    $geshi->set_code_style('color: #000020;', 'color: #000020;');
    $geshi->set_link_styles(GESHI_LINK, 'color: #000060;');
    $geshi->set_link_styles(GESHI_HOVER, 'background-color: #f0f000;');

    // save generated stylesheet
    $element->texy->styleSheet .= $geshi->get_stylesheet();

    $out = $geshi->parse_code();
    if ($element->texy->utf)  // double-check buggy GESHI, it sometimes produce not UTF-8 valid code :-((
        if ($out !== utf8_encode(utf8_decode($out))) return;

    $element->setContent($out, TRUE);
    $element->tag = FALSE;
}





$texy = &new Texy();

// set user callback function for /-- code blocks
$texy->blockModule->codeHandler = 'myUserFunc';
// prepare CSS stylesheet
$texy->styleSheet = 'pre { padding:10px } ';

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
echo '<style type="text/css">'. $texy->styleSheet . '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>