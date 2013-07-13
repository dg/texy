<?php

/**
 * This demo shows how Texy! control inline html tags
 *     - three safe levels
 *     - full control over all tags and attributes
 *     - (X)HTML reformatting
 *     - well formed output
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


$texy = new Texy();
$texy->htmlOutputModule->baseIndent  = 1;


function doIt($texy)
{
	// processing
	$text = file_get_contents('sample.texy');
	$html = $texy->process($text);  // that's all folks!

	// echo formated output
	echo $html;

	// and echo generated HTML code
	echo '<pre>';
	echo htmlSpecialChars($html);
	echo '</pre>';
	echo '<hr />';
}


header('Content-type: text/html; charset=utf-8');

echo '<h2>Enable nearly all valid tags</h2>';
// by default
doIt($texy);

echo '<h2>Texy::ALL - enables all tags</h2>';
$texy->allowedTags = Texy::ALL;
doIt($texy);

echo '<h2>safeMode() - enables only some "safe" tags</h2>';
TexyConfigurator::safeMode($texy);
doIt($texy);

echo '<h2>disableLinks() - disable all links</h2>';
TexyConfigurator::disableLinks($texy);
doIt($texy);

echo '<h2>Texy::NONE - disables all tags</h2>';
$texy->allowedTags = Texy::NONE;
doIt($texy);

echo '<h2>Enable custom tags</h2>';
$texy->allowedTags =
	array(            // enable only tags <myExtraTag> with attribute & <strong>
		'myExtraTag' => array('attr1'),
		'strong'     => array(),
	);
doIt($texy);
