<?php declare(strict_types=1);

/**
 * GETTING STARTED WITH TEXY
 *
 * This is the simplest example showing how to use Texy in 3 steps:
 * 1. Create a Texy instance
 * 2. Configure it (optional)
 * 3. Process your text
 *
 * WHAT YOU'LL LEARN:
 * - Basic Texy setup
 * - How to set the image folder path
 * - How to enable additional text formatting (++inserted++, --deleted--, etc.)
 * - How to get the document title for your <title> tag
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


// STEP 1: Create a Texy instance
$texy = new Texy;


// STEP 2: Configure Texy (all of this is optional)

// Tell Texy where your images are located
$texy->imageModule->root = 'images/';

// Enable some text formatting that is disabled by default:
// ++inserted text++ renders as <ins>
// --deleted text-- renders as <del>
// ^^superscript^^ renders as <sup>
// __subscript__ renders as <sub>
// visual ""citation"" renders as <cite>
$texy->allowed['phrase/ins'] = true;
$texy->allowed['phrase/del'] = true;
$texy->allowed['phrase/sup'] = true;
$texy->allowed['phrase/sub'] = true;
$texy->allowed['phrase/cite'] = true;


// STEP 3: Process your Texy text
$text = file_get_contents(__DIR__ . '/syntax.texy');
$html = $texy->process($text);  // That's it!


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';

// You can use the first heading from your document as the page title
echo '<title>' . $texy->headingModule->title . '</title>';

echo $html;


// Show the generated HTML source code (for learning purposes)
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
