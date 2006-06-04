<?php

/**
 * TEXY! CACHE DEMO
 * --------------------------------------
 *
 * This demo shows how cache Texy! output and
 * demonstrates advantages of inheriting from base Texy object
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 */


require_once dirname(__FILE__).'/mytexy.php';


$texy = &new MyTexy();

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);


// echo formated output
header("Content-type: text/html; charset=windows-1250");
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

?>