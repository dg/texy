<?php

/**
 * TEXY! 1-2-3 START DEMO
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


$texy = new Texy();

// other OPTIONAL configuration
$texy->encoding = 'windows-1250';      // disable UTF-8
$texy->imageModule->root = 'images/';  // specify image folder
$texy->allowed['phrase/ins'] = TRUE;
$texy->allowed['phrase/del'] = TRUE;
$texy->allowed['phrase/sup'] = TRUE;
$texy->allowed['phrase/sub'] = TRUE;
$texy->allowed['phrase/cite'] = TRUE;


// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=' . $texy->encoding);
echo '<link rel="stylesheet" type="text/css" media="all" href="style.css" />';
echo '<title>' . $texy->headingModule->title . '</title>';
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
