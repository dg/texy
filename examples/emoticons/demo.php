<?php declare(strict_types=1);

/**
 * ADDING SMILEYS (EMOTICONS) TO YOUR TEXT
 *
 * This example shows how to enable and customize emoticons in Texy.
 * By default, emoticons are DISABLED - you need to explicitly enable them.
 *
 * WHAT YOU'LL LEARN:
 * - How to enable emoticons (they're off by default)
 * - How to set a CSS class for emoticon images
 * - How to add your own custom emoticons
 *
 * BUILT-IN EMOTICONS:
 * :-) :-)  smile
 * :-(      sad
 * ;-)      wink
 * :-D      big grin
 * 8-)      cool
 * :-P      tongue out
 * and more...
 */


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;


// IMPORTANT: Emoticons are disabled by default!
// You must enable them like this:
$texy->allowed['emoticon'] = true;

// Set a CSS class that will be added to all emoticon images
$texy->emoticonModule->class = 'smilie';

// Add your own custom emoticon
// When someone types :oops: in the text, it will show redface.gif
$texy->emoticonModule->icons[':oops:'] = 'redface.gif';


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->process($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo '<style type="text/css"> .smiley { vertical-align: middle; } </style>';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
