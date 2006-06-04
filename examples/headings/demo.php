<?php

/**
 * -----------------------
 *   TEXY! HEADINGS DEMO
 * -----------------------
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
$text = file_get_contents('sample.texy');


// 1) Dynamic method

$texy->headingModule->top       = 2;   // set headings top limit
$texy->headingModule->balancing = TEXY_HEADING_DYNAMIC; // this is default

$html = $texy->process($text);  // that's all folks!

// echo topmost heading (text is html safe!)
echo '<title>' . $texy->headingModule->title . '</title>';

// and echo generated HTML code
echo '<strong>Dynamic method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';




// 2) Fixed method

$texy->headingModule->top       = 1;   // set headings top limit
$texy->headingModule->balancing = TEXY_HEADING_FIXED;

$html = $texy->process($text);  // that's all folks!

// and echo generated HTML code
echo '<strong>Fixed method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';




// 3) User-defined fixed method

$texy->headingModule->top       = 1;   // set headings top limit
$texy->headingModule->balancing = TEXY_HEADING_FIXED;
$texy->headingModule->levels['='] = 0;  // = means 0; top=1;       0 + 1 = 1 (h1)
$texy->headingModule->levels['-'] = 1;  // - means 1; top=1;       1 + 1 = 2 (h2)
$texy->headingModule->levels[5] = 2;    // ##### means 2; top=1;   2 + 1 = 3 (h3)

$html = $texy->process($text);  // that's all folks!

// and echo generated HTML code
echo '<strong>User-defined fixed method:</strong>';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
echo '<hr />';





?>