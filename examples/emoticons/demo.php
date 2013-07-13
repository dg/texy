<?php

/**
 * This demo shows how enable emoticons in Texy!
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


$texy = new Texy();


// EMOTICONS ARE DISABLED BY DEFAULT!
$texy->allowed['emoticon'] = TRUE;

// configure it
$texy->emoticonModule->class = 'smilie';
$texy->emoticonModule->icons[':oops:'] = 'redface.gif';  // user-defined emoticon


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// CSS style
header('Content-type: text/html; charset=utf-8');
echo '<style type="text/css"> .smiley { vertical-align: middle; } </style>';

// echo formated output
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
