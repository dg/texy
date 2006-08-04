<?php

/**
 * TEXY! MODIFIERS DEMO
 * --------------------------------------
 *
 * This demo shows how control modifiers usage
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */




// include Texy!
$texyPath = '../../texy/';
require_once ($texyPath . 'texy.php');



$texy = &new Texy();
$texy->formatterModule->baseIndent  = 1;



function doIt() {
    global $texy;

    // processing
    $text = file_get_contents('sample.texy');
    $html = $texy->process($text);  // that's all folks!

    // echo formated output
    echo $html;

    // and echo generated HTML code
    echo '<pre>';
    echo htmlSpecialChars($html);
    echo '</pre>';
    echo '<hr />';
}




echo '<h2>mode: Styles and Classes allowed (default)</h2>';
$texy->allowedClasses = TRUE;
$texy->allowedStyles  = TRUE;
doIt();

echo '<h2>mode: Styles and Classes disabled</h2>';
$texy->allowedClasses = FALSE;
$texy->allowedStyles  = FALSE;
doIt();

echo '<h2>mode: Custom</h2>';
$texy->allowedClasses = array('one', '#id');
$texy->allowedStyles  = array('color');
doIt();

?>