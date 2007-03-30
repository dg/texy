<?php

/**
 * TEXY! LINKS DEMO
 * --------------------------------------
 *
 * This demo shows how control links in Texy!
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

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @param TexyModifier
     * @param TexyLink
     * @return TexyHtml|string|FALSE|NULL
     */
    public function phrase($parser, $phrase, $content, $modifier, $link)
    {
        // is there link?
        if (!$link) return Texy::PROCEED;

        if (Texy::isRelative($link->URL)) {
            // modifiy link
            $link->URL = 'index?page=' . urlencode($link->URL);

        } elseif (substr($link->URL, 0, 5) === 'wiki:') {
            // modifiy link
            $link->URL = 'http://en.wikipedia.org/wiki/Special:Search?search=' . urlencode(substr($link->URL, 5));
        }

        return Texy::PROCEED;
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
