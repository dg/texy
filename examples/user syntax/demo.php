<?php

/**
 * TEXY! USER SYNTAX DEMO
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */


// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



$texy = new Texy();

// disable *** and ** and * phrases
$texy->allowed['phraseStrongEm'] = FALSE;
$texy->allowed['phraseStrong'] = FALSE;
$texy->allowed['phraseEm'] = FALSE;

$texy->allowed['mySyntax1'] = TRUE;
$texy->allowed['mySyntax2'] = TRUE;


die('NOT WORKING YET.');

// add new syntax: *bold* _italic_
$texy->registerLinePattern(
    $texy->phraseModule,
    'processPhrase',
    '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
//    '#(?<!\*)\*(?!\ )([^\*]+)<MODIFIER>?(?<!\ )\*(?!\*)()#U',
    'mySyntax1'
);

$texy->registerLinePattern(
    $texy->phraseModule,
    'processPhrase',
    '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
//    '#(?<!\_)\_(?!\ )([^\_]+)<MODIFIER>?(?<!\ )\_(?!\_)()#U',
    'mySyntax2'
);



// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!

// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>