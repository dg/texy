<?php

/**
 * TEXY! HTML TAGS DEMO
 * --------------------------------------
 *
 * This demo shows how Texy! control inline html tags
 *     - three safe levels
 *     - full control over all tags and attributes
 *     - (X)HTML reformatting
 *     - well formed output
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */



// include Texy!
$texyPath = '../../texy/';
require_once ($texyPath . 'texy.php');



$texy = new Texy();
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




echo '<h2>trustMode() - enable all valid tags</h2>';
$texy->htmlModule->trustMode();
doIt();

echo '<h2>trustMode(FALSE) - enable all tags</h2>';
$texy->htmlModule->trustMode(FALSE);
doIt();

echo '<h2>safeMode() - enable only "safe" tags</h2>';
$texy->htmlModule->safeMode();
doIt();

echo '<h2>safeMode(FALSE) - disable all tags</h2>';
$texy->htmlModule->safeMode(FALSE);
doIt();

echo '<h2>custom</h2>';
$texy->allowedTags =
     array(            // enable only tags <myExtraTag> with attribute & <strong>
         'myExtraTag' => array('attr1'),
         'strong'     => array(),
     );
doIt();

?>