<?php

/**
 * TEXY! REFERENCES DEMO
 * --------------------------------------
 *
 * This demo shows how implement Texy! as comment formatter
 *     - relative links to other comment
 *     - rel="nofollow"
 *     - used links checking (antispam)
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





// this is user callback function for processing 'link references' [xxxx]
// returns FALSE or TexyLinkReference

function &myUserFunc($refName, &$texy) {
    $names = array('Me', 'Punkrats', 'Serwhats', 'Bonnyfats');

    if (!isset($names[$refName]))
        return FALSE;              // it's not my job

    $name  = $names[$refName];  // some range checing

      // this function must return TexyLinkReference object (or FALSE, of course)
    $elRef = &new TexyLinkReference($texy);

    $elRef->URL = '#comm-' . $refName; // set link destination
    $elRef->label = '[' . $refName . '] **' . $name . '**';   // set link label (with Texy formatting)
    $elRef->modifier->classes[] = 'comment';  // set modifier, e.g. class name

    // to enable rel="nofollow", set this:   $elRef->modifier->classes[] = 'nofollow';

    return $elRef;
}






$texy = &new Texy();

// configuration
$texy->referenceHandler = 'myUserFunc';   // references link [1] [2] will be processed through user function
$texy->safeMode();                        // safe mode prevets attacker to inject some HTML code and disable images

// how generally disable links or enable images? here is a way:
//      $texy->imageModule->allowed = TRUE;
//      $texy->linkModule->allowed = FALSE;


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
$spam = FALSE;
foreach ($texy->summary->links as $link)
    if (strpos($link, 'casino')) {
        $spam = TRUE;
        break;
    }


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>