<?php

/**
 * --------------------------
 *   TEXY! USER SYNTAX DEMO
 * --------------------------
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>. All rights reserved.
 * Web: http://www.texy.info/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');




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