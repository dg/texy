<?php declare(strict_types=1);

/**
 * SECURITY - CONTROLLING ALLOWED HTML TAGS
 *
 * This is one of the most important examples for security!
 * It shows how to control which HTML tags users can include in their text.
 *
 * WHAT YOU'LL LEARN:
 * - Different security levels from permissive to strict
 * - safeMode() - the recommended setting for user-submitted content
 * - How to disable all links or images
 * - How to create a custom whitelist of allowed tags
 *
 * SECURITY LEVELS (from most to least permissive):
 * 1. Texy::ALL - allows ALL HTML tags (dangerous for user input!)
 * 2. Default - allows most valid HTML5 tags
 * 3. safeMode() - allows only safe tags (recommended for comments/forums)
 * 4. Texy::NONE - allows NO HTML tags at all
 * 5. Custom whitelist - you specify exactly which tags are allowed
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
// DEFAULT MODE
// Most valid HTML5 tags are allowed, but dangerous attributes
// like onclick are removed.
// ============================================================
echo '<h2>Enable nearly all valid tags</h2>';
doIt($texy);


// ============================================================
// Texy::ALL
// Allows ALL HTML tags, including custom elements.
// WARNING: Only use this if you trust the input completely!
// ============================================================
echo '<h2>Texy::ALL - enables all tags</h2>';
$texy->allowedTags = $texy::ALL;
doIt($texy);


// ============================================================
// SAFE MODE (RECOMMENDED FOR USER INPUT!)
// Only allows safe tags like <strong>, <em>, <a>, <code>, etc.
// Removes images, scripts, styles, and dangerous attributes.
// ============================================================
echo '<h2>safeMode() - enables only some "safe" tags</h2>';
Texy\Configurator::safeMode($texy);
doIt($texy);


// ============================================================
// DISABLE LINKS
// Use this if you don't want users to include any links.
// ============================================================
echo '<h2>disableLinks() - disable all links</h2>';
Texy\Configurator::disableLinks($texy);
doIt($texy);


// ============================================================
// Texy::NONE
// Completely disables all HTML tags.
// Tags in input will be escaped and shown as text.
// ============================================================
echo '<h2>Texy::NONE - disables all tags</h2>';
$texy->allowedTags = $texy::NONE;
doIt($texy);


// ============================================================
// CUSTOM WHITELIST
// Specify exactly which tags and attributes are allowed.
// Format: 'tag-name' => ['allowed', 'attributes']
// Empty array [] means no attributes are allowed.
// ============================================================
echo '<h2>Enable custom tags</h2>';
$texy->allowedTags = [
	'my-extraTag' => ['attr1'],  // Allow <my-extraTag attr1="...">
	'strong' => [],              // Allow <strong> with no attributes
];
doIt($texy);
