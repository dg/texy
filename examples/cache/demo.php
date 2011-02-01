<?php

/**
 * This demo shows how cache Texy! output and
 * demonstrates advantages of inheriting from base Texy object
 */


require_once dirname(__FILE__).'/mytexy.php';


$texy = new MyTexy();

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo '<title>' . $texy->headingModule->title . '</title>';

// echo $texy->time
echo '<strong>' . number_format($texy->time, 3, ',', ' ') . 'sec</strong>';
echo '<br />';


// echo formated output
echo $html;

// echo HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
