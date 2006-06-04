<?php

/**
 * TEXY! SMILIES DEMO
 * --------------------------------------
 *
 * This demo shows how enable smilies in Texy!
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 */


// include Texy!
$texyPath = '../../texy/';
require_once ($texyPath . 'texy.php');



$texy = &new Texy();


// SMILIES ARE DISABLED BY DEFAULT!
$texy->smiliesModule->allowed = TRUE;
// configure it
$texy->smiliesModule->root  = 'images/';
$texy->smiliesModule->class  = 'smilie';
$texy->smiliesModule->icons[':oops:'] = 'redface.gif';  // user-defined smilie



// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// CSS style
echo '<style type="text/css"> .smiley { vertical-align: middle; } </style>';

// echo formated output
echo $html;


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>