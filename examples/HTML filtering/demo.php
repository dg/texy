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
 * - How to disable all links
 * - How to create a custom whitelist of allowed tags
 *
 * SECURITY LEVELS (from most to least permissive):
 * 1. true - allows ALL HTML tags (dangerous for user input!)
 * 2. Default - allows the common HTML tags with any attributes (dangerous for user input too)
 * 3. safeMode() - allows only safe tags (recommended for comments/forums)
 * 4. false - allows NO HTML tags at all
 * 5. Custom whitelist - you specify exactly which tags are allowed
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


/**
 * Processes the sample with a fresh Texy set up by $configure, so that each mode is shown on its own.
 * @param  ?Closure(Texy\Texy): mixed  $configure
 */
function show(string $title, ?Closure $configure = null): void
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->baseIndent = 1;
	if ($configure) {
		$configure($texy);
	}
	$html = $texy->process(file_get_contents(__DIR__ . '/sample.texy'));

	// Show the rendered output and its HTML source code
	echo "<h2>$title</h2>";
	echo $html;
	echo '<pre>', htmlspecialchars($html), '</pre>';
	echo '<hr>';
}


echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';


// ============================================================
// DEFAULT MODE
// The common HTML tags are allowed with all their attributes, event
// handlers like onmouseover included. Fine for trusted text only.
// ============================================================
show('Enable nearly all valid tags');


// ============================================================
// ALL TAGS
// Allows ALL HTML tags, including custom elements.
// WARNING: Only use this if you trust the input completely!
// ============================================================
show('allowedTags = true - enables all tags', fn(Texy\Texy $texy) => $texy->allowedTags = true);


// ============================================================
// SAFE MODE (RECOMMENDED FOR USER INPUT!)
// Only allows safe tags like <strong>, <em>, <a>, <code>, etc.
// Removes images, scripts, styles, and dangerous attributes.
// ============================================================
show('safeMode() - enables only some "safe" tags', fn(Texy\Texy $texy) => Texy\Configurator::safeMode($texy));


// ============================================================
// DISABLE LINKS
// Use this if you don't want users to include any links.
// ============================================================
show('disableLinks() - disable all links', fn(Texy\Texy $texy) => Texy\Configurator::disableLinks($texy));


// ============================================================
// NO TAGS
// Completely disables all HTML tags.
// Tags in input will be escaped and shown as text.
// ============================================================
show('allowedTags = false - disables all tags', fn(Texy\Texy $texy) => $texy->allowedTags = false);


// ============================================================
// CUSTOM WHITELIST
// Specify exactly which tags and attributes are allowed.
// Format: 'tag-name' => ['allowed', 'attributes']
// Empty array [] means no attributes are allowed.
// ============================================================
show('Enable custom tags', fn(Texy\Texy $texy) => $texy->allowedTags = [
	'my-extraTag' => ['attr1'],  // Allow <my-extraTag attr1="...">
	'strong' => [],              // Allow <strong> with no attributes
]);
