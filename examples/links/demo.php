<?php

/**
 * This demo shows how control links in Texy!
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


/**
 * @param TexyHandlerInvocation  handler invocation
 * @param string
 * @param string
 * @param TexyModifier
 * @param TexyLink
 * @return TexyHtml|string|FALSE
 */
function phraseHandler($invocation, $phrase, $content, $modifier, $link)
{
	// is there link?
	if (!$link) return $invocation->proceed();

	if (Texy::isRelative($link->URL)) {
		// modifiy link
		$link->URL = 'index?page=' . urlencode($link->URL);

	} elseif (substr($link->URL, 0, 5) === 'wiki:') {
		// modifiy link
		$link->URL = 'http://en.wikipedia.org/wiki/Special:Search?search=' . urlencode(substr($link->URL, 5));
	}

	return $invocation->proceed();
}


$texy = new Texy();

// configuration
$texy->addHandler('phrase', 'phraseHandler');

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
