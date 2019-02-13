<?php

/**
 * This demo shows how control modifiers usage
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;
$texy->htmlOutputModule->baseIndent = 1;


function doIt($texy)
{
	// processing
	$text = file_get_contents('sample.texy');
	$html = $texy->process($text);  // that's all folks!

	// echo formated output
	echo $html;

	// and echo generated HTML code
	echo '<pre>';
	echo htmlspecialchars($html);
	echo '</pre>';
	echo '<hr />';
}


header('Content-type: text/html; charset=utf-8');

echo '<h2>mode: Styles and Classes allowed (default)</h2>';
$texy->allowedClasses = true;
$texy->allowedStyles = true;
doIt($texy);

echo '<h2>mode: Styles and Classes disabled</h2>';
$texy->allowedClasses = false;
$texy->allowedStyles = false;
doIt($texy);

echo '<h2>mode: Custom</h2>';
$texy->allowedClasses = ['one', '#id'];
$texy->allowedStyles = ['color'];
doIt($texy);
