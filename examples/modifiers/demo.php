<?php declare(strict_types=1);

/**
 * CONTROLLING CSS CLASSES AND INLINE STYLES
 *
 * In Texy, users can add CSS classes and inline styles to elements using
 * "modifiers" - special syntax in brackets. This example shows how to
 * control whether these modifiers are allowed.
 *
 * WHAT YOU'LL LEARN:
 * - How to allow or disallow CSS classes and inline styles
 * - How to create a whitelist of specific allowed classes/styles
 * - Why this matters for security with user input
 *
 * TEXY MODIFIER SYNTAX:
 * **text** .{color:red}           - adds inline style
 * **text** .[myclass]             - adds CSS class
 * **text** .[myclass #myid]       - adds class and ID
 * **text** .{color:red}[myclass]  - combines both
 *
 * SECURITY NOTE:
 * If you're processing user input, consider using allowedClasses and
 * allowedStyles to limit what CSS users can inject into your page.
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;
$texy->htmlOutputModule->baseIndent = 1;


/**
 * Helper function to process and display the text
 */
function doIt($texy)
{
	$text = file_get_contents(__DIR__ . '/sample.texy');
	$html = $texy->process($text);

	// Show the rendered output
	echo $html;

	// Show the HTML source code
	echo '<pre>';
	echo htmlspecialchars($html);
	echo '</pre>';
	echo '<hr>';
}


echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';


// ============================================================
// MODE 1: ALLOW EVERYTHING (DEFAULT)
// All CSS classes and inline styles are allowed.
// ============================================================
echo '<h2>mode: Styles and Classes allowed (default)</h2>';
$texy->allowedClasses = true;
$texy->allowedStyles = true;
doIt($texy);


// ============================================================
// MODE 2: DISABLE EVERYTHING
// No CSS classes or inline styles are allowed.
// The modifier syntax is ignored.
// ============================================================
echo '<h2>mode: Styles and Classes disabled</h2>';
$texy->allowedClasses = false;
$texy->allowedStyles = false;
doIt($texy);


// ============================================================
// MODE 3: CUSTOM WHITELIST
// Only specific classes and styles are allowed.
// - Classes: only "one" and ID "id" are allowed
// - Styles: only "color" property is allowed
// ============================================================
echo '<h2>mode: Custom</h2>';
$texy->allowedClasses = ['one', '#id'];  // Note: IDs are prefixed with #
$texy->allowedStyles = ['color'];
doIt($texy);
