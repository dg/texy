<?php

/**
 * This demo shows how to use Texy! to
 *     - hyphenate long words
 *     - insert non-breaking spaces
 *     - make typographic corrections
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;
$texy->allowed['longwords'] = true; // hyphenate long words

// processing
$text = file_get_contents('sample.texy');
$html = $texy->processTypo($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;
