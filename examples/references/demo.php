<?php

/**
 * This demo shows how implement Texy! as comment formatter
 *     - relative links to other comment
 *     - rel="nofollow"
 *     - used links checking (antispam)
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


/**
 * User handler for unknown reference
 *
 * @param TexyHandlerInvocation  handler invocation
 * @param string   [refName]
 * @return TexyHtml|string
 */
function newReferenceHandler($parser, $refName)
{
	$names = array('Me', 'Punkrats', 'Servats', 'Bonifats');

	if (!isset($names[$refName])) return FALSE; // it's not my job

	$name = $names[$refName];

	$el = TexyHtml::el('a');
	$el->attrs['href'] = '#comm-' . $refName; // set link destination
	$el->attrs['class'][] = 'comment';        // set class name
	$el->attrs['rel'] = 'nofollow';           // enable rel="nofollow"
	$el->setText("[$refName] $name:"); // set link label (with Texy formatting)
	return $el;
}


$texy = new Texy();

// references link [1] [2] will be processed through user function
$texy->addHandler('newReference', 'newReferenceHandler');

// configuration
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
foreach ($texy->summary['links'] as $link) {
	if (strpos($link, 'casino')) {
		$spam = TRUE;
		break;
	}
}


// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
