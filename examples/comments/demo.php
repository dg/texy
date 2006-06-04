<?php

/**
 * -----------------------
 *   TEXY! COMMENTS DEMO
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


/**
 *  This demo shows how implement Texy! as comment formatter
 *     - relative links to other comment
 *     - rel="nofollow"
 *     - used links checking (antispam)
 */


// check required version
if (version_compare(phpversion(), '4.3.3', '<'))
  die('Texy! requires PHP version 4.3.3 or higher');




// include Texy!
$texyPath = '../../texy/';
require_once($texyPath . 'texy.php');





// this is user callback function for processing 'link references' [xxxx]
// returns false or TexyLinkReference

function &myUserFunc(&$texy, $refName) {
  $names = array('Me', 'Punkrats', 'Servats', 'Bonifats');

  if (!isset($names[$refName]))
    return false;              // it's not my job

  $name  = $names[$refName];  // some range checing

    // this function must return TexyLinkReference object (or false, of course)
  $elRef = &new TexyLinkReference($texy);

  $elRef->URL = '#comm-' . $refName; // set link destination
  $elRef->label = '[' . $refName . '] **' . $name . '**';   // set link label (with Texy formatting)
  $elRef->modifier->classes[] = 'comment';  // set modifier, e.g. class name

  // to enable rel="nofollow", set this:   $elRef->modifier->classes[] = 'nofollow';

  return $elRef;
}






$texy = &new Texy();

// configuration
$texy->links->userReferences = 'myUserFunc';  // references link [1] [2] will be processed through user function
$texy->safeMode();                            // safe mode prevets attacker to inject some HTML code and disable images

// how generally disable links or enable images? here is a way:
//      $texy->images->allowed = true;
//      $texy->links->allowed = false;


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
echo $html;


// echo all embedded links
echo '<hr />';
echo '<pre>';
print_r($texy->summary->links);
echo '</pre>';


// do some antispam filtering - this is just very simple example ;-)
$spam = false;
foreach ($texy->summary->links as $link)
  if (strpos($link, 'casino')) {
    $spam = true;
    break;
  }


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>