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
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';





// this is user callback object for processing Texy events
class myHandler
{

    /**
     * User handler for unknown reference
     *
     * @param TexyLineParser
     * @param string   [refName]
     * @return TexyHtml|string
     */
    function newReference($parser, $refName)
    {
        $names = array('Me', 'Punkrats', 'Servats', 'Bonifats');

        if (!isset($names[$refName])) return FALSE; // it's not my job

        $name = $names[$refName];  // some range checing

        $el = TexyHtml::el('a');
        $el->attrs['href'] = '#comm-' . $refName; // set link destination
        $el->attrs['class'][] = 'comment';        // set class name
        $el->attrs['rel'] = 'nofollow';           // enable rel="nofollow"
        $el->setText("[$refName] $name:"); // set link label (with Texy formatting)
        return $el;
    }

}







$texy = new Texy();

// configuration
$texy->handler = new myHandler;  // references link [1] [2] will be processed through user function
TexyConfigurator::safeMode($texy);     // safe mode prevets attacker to inject some HTML code and disable images

// how generally disable links or enable images? here is a way:
//    $disallow = array('image', 'figure', 'linkReference', 'linkEmail', 'linkURL', 'linkQuick');
//    foreach ($diallow as $item)
//        $texy->allowed[$item] = FALSE;


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;


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
