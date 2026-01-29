<?php

/**
 * BUILDING A COMMENT SYSTEM WITH REFERENCES
 *
 * This example shows how to use Texy to format user comments safely,
 * with support for referencing other comments using [1], [2], etc.
 *
 * WHAT YOU'LL LEARN:
 * - How to handle undefined references (like [1], [2]) to create mentions
 * - How to add rel="nofollow" to links for spam prevention
 * - How to use safeMode() for safe processing of user comments
 * - Simple spam detection by checking link URLs
 *
 * USE CASE:
 * In a forum or comment system, users can reference other comments:
 * "I agree with [1] but [2] is wrong."
 * This becomes links to #comm-1 and #comm-2 on the page.
 */

declare(strict_types=1);

use Texy\Nodes\LinkReferenceNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;


// Register our reference handler
// The first parameter type (LinkReferenceNode) determines which node class the handler processes
$texy->htmlGenerator->registerHandler(
	function (LinkReferenceNode $node, Html\Generator $gen) use ($texy): Html\Element|string {
		// Map of comment IDs to author names
		$names = ['Me', 'Punkrats', 'Servats', 'Bonifats'];
		$refName = $node->identifier;

		// Only handle numeric references that exist in our list
		if (!isset($names[$refName])) {
			// Not found - render as plain text [identifier]
			return '[' . htmlspecialchars($refName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ']';
		}

		$name = $names[$refName];

		// Create a link to the comment anchor
		$el = new Html\Element('a');
		$el->attrs['href'] = '#comm-' . $refName;  // Links to <div id="comm-1">
		$el->attrs['class'][] = 'comment';
		$el->attrs['rel'] = 'nofollow';            // Important for SEO/spam prevention
		$el->setText("[$refName] $name:");

		return $el;
	},
);

// IMPORTANT: Use safe mode for user-submitted content!
// This prevents XSS attacks by:
// - Allowing only safe HTML tags
// - Disabling images (which could be used for tracking)
// - Adding rel="nofollow" to links
Texy\Configurator::safeMode($texy);

// Alternative: If you want to disable specific features manually:
// $texy->allowed[Texy\Syntax::Image] = false;
// $texy->allowed[Texy\Syntax::Figure] = false;
// $texy->allowed[Texy\Syntax::AutolinkUrl] = false;


// Process the comment text
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->process($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
