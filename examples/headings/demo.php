<?php

/**
 * TEXY! HEADINGS DEMO
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


$texy = new Texy();
$text = file_get_contents('sample.texy');


// 1) Dynamic method

$texy->headingModule->top       = 2;   // set headings top limit
$texy->headingModule->balancing = TexyHeadingModule::DYNAMIC;

// generate ID
$texy->headingModule->generateID = TRUE;


$html = $texy->process($text);  // that's all folks!

// echo topmost heading (text is html safe!)
header('Content-type: text/html; charset=utf-8');
echo '<title>' . $texy->headingModule->title . '</title>';

// and echo generated HTML code
echo '<strong>Dynamic method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';


// 2) Fixed method

$texy->headingModule->top       = 1;   // set headings top limit
$texy->headingModule->balancing = TexyHeadingModule::FIXED;

$html = $texy->process($text);  // that's all folks!

// and echo generated HTML code
echo '<strong>Fixed method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';


// 3) User-defined fixed method

$texy->headingModule->top       = 1;   // set headings top limit
$texy->headingModule->balancing = TexyHeadingModule::FIXED;

$texy->headingModule->levels['='] = 0;  // = means 0 + top (1) = 1 (h1)
$texy->headingModule->levels['-'] = 1;  // - means 1 + top (1) = 2 (h2)

$html = $texy->process($text);  // that's all folks!

// and echo generated HTML code
echo '<strong>User-defined fixed method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';


// and echo TOC
echo '<h2>Table of contents</h2>';
echo '<pre>';
print_r($texy->headingModule->TOC);
echo '</pre>';
