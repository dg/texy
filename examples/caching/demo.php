<?php

/**
 * --------------------
 *   TEXY! CACHE DEMO
 * --------------------
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


/**
 *  This demo shows how cache Texy! output and
 *  demonstrates advantages of inheriting from base Texy object
 */



// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');


require_once('mytexy.php');


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