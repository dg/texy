<?php

/**
 * TEXY! THIRD PARTY SYNTAX HIGHLIGHTING
 * --------------------------------------
 *
 * This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */

$texyPath = dirname(__FILE__).'/../../texy/';
$fshlPath = dirname(__FILE__).'/fshl/';


// include libs
require_once ($texyPath . 'texy.php');
include_once ($fshlPath . 'fshl.php');





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
function myUserFunc($element) {
    $lang = strtoupper($element->lang);
    if ($lang == 'JAVASCRIPT') $lang = 'JS';
    if (!in_array(
            $lang,
            array('CPP', 'CSS', 'HTML', 'JAVA', 'PHP', 'JS', 'SQL'))
       ) return;

    $parser = new fshlParser($element->texy->utf ? 'HTML_UTF8' : 'HTML', P_TAB_INDENT);
    $element->setContent($parser->highlightString($lang, $element->getContent()), TRUE);
}






$texy = new Texy();

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