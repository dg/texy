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
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';





// this is user callback object for processing Texy events
class myHandler
{

    // reference handler
    function reference($texy, $refName)
    {
        $names = array('Me', 'Punkrats', 'Servats', 'Bonifats');

        if (!isset($names[$refName])) return FALSE; // it's not my job

        $name = $names[$refName];  // some range checing

        $el = TexyHtml::el('a');
        $el->href = '#comm-' . $refName; // set link destination
        $el->class[] = 'comment';        // set class name
        $el->rel = 'nofollow';           // enable rel="nofollow"
        $el->setContent("[$refName] $name:");  // set link label (with Texy formatting)
        return $el;
    }

}







$texy = new Texy();

// configuration
$texy->handler = new myHandler;  // references link [1] [2] will be processed through user function
$texy->safeMode();               // safe mode prevets attacker to inject some HTML code and disable images

// how generally disable links or enable images? here is a way:
//      $texy->imageModule->allowed = TRUE;
//      $texy->linkModule->allowed = FALSE;


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;


// echo all embedded links
echo '<hr />';
echo '<pre>';
print_r($texy->summary['links']);
echo '</pre>';


// do some antispam filtering - this is just very simple example ;-)
$spam = FALSE;
foreach ($texy->summary['links'] as $link)
    if (strpos($link, 'casino')) {
        $spam = TRUE;
        break;
    }


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
