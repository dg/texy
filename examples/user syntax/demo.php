<?php

/**
 * TEXY! USER SYNTAX DEMO
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 */


// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



$texy = &new Texy();

// disable *** and ** and * phrases
$texy->phraseModule->allowed['***'] = false;
$texy->phraseModule->allowed['**'] = false;
$texy->phraseModule->allowed['*'] = false;

// add new syntax: *bold* _italic_
$texy->phraseModule->registerLinePattern('processPhrase', '#(?<!\*)\*(?!\ )([^\*]+)MODIFIER?(?<!\ )\*(?!\*)()#U', 'b');
$texy->phraseModule->registerLinePattern('processPhrase', '#(?<!\_)\_(?!\ )([^\_]+)MODIFIER?(?<!\ )\_(?!\_)()#U', 'i');



// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!

// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>