<?php

/**
 * TEXY! LINKS DEMO
 * --------------------------------------
 *
 * This demo shows how control links in Texy!
 *
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';





// this is user callback object for processing Texy events
class myHandler
{

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @param TexyModifier
     * @param TexyLink
     * @return TexyHtml|string|FALSE|NULL
     */
    function phrase($parser, $phrase, $content, $modifier, $link)
    {
        // is there link?
        if (!$link) return TEXY_PROCEED; // or Texy::PROCEED in PHP 5

        if (Texy::isRelative($link->URL)) {
            // modifiy link
            $link->URL = 'index?page=' . urlencode($link->URL);

        } elseif (substr($link->URL, 0, 5) === 'wiki:') {
            // modifiy link
            $link->URL = 'http://en.wikipedia.org/wiki/Special:Search?search=' . urlencode(substr($link->URL, 5));
        }

        return TEXY_PROCEED; // or Texy::PROCEED in PHP 5
    }

}



$texy = new Texy();

// configuration
$texy->handler = new myHandler;

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


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
