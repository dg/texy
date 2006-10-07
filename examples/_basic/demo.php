<?php

/**
 * TEXY! BASIC DEMO
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */


// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



$texy = new Texy();

// other OPTIONAL configuration
$texy->utf = FALSE;                    // disable UTF-8
$texy->imageModule->root = 'images/';  // specify image folder


// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=windows-1250');
echo '<link rel="stylesheet" type="text/css" media="all" href="style.css" />';
echo '<title>' . $texy->headingModule->title . '</title>';
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>