<?php

/**
 * CUSTOMIZING LINK BEHAVIOR
 *
 * This example shows how to transform links in your Texy text.
 * Common use cases:
 * - Convert relative links to full URLs
 * - Handle custom URL schemes (like "wiki:topic")
 * - Add tracking parameters to external links
 *
 * WHAT YOU'LL LEARN:
 * - How to intercept and modify links using an HTML handler
 * - How to detect relative vs. absolute URLs
 * - How to handle custom URL schemes
 * - How to get a list of all links in the document
 *
 * TEXY LINK SYNTAX:
 * "link text":url           - basic link
 * "link text":relative-page - relative link (transformed by our handler)
 * "link text":wiki:topic    - custom scheme (transformed to Wikipedia)
 */

declare(strict_types=1);

use Texy\Helpers;
use Texy\Nodes\LinkNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;


// Register our custom link handler
// The first parameter type (LinkNode) determines which node class the handler processes
$texy->htmlGenerator->registerHandler(
	function (LinkNode $node, Html\Generator $gen) use ($texy): Html\Element {
		$url = $node->url ?? '';

		// Check if this is a relative link (doesn't contain "://")
		if (Helpers::isRelative($url)) {
			// Transform relative links to your CMS URL format
			// "my-page" becomes "index?page=my-page"
			$url = 'index?page=' . urlencode($url);

		} elseif (str_starts_with($url, 'wiki:')) {
			// Handle our custom "wiki:" URL scheme
			// "wiki:texy" becomes a Wikipedia search link
			$searchTerm = substr($url, 5);  // Remove "wiki:" prefix
			$url = 'https://en.wikipedia.org/wiki/Special:Search?search=' . urlencode($searchTerm);
		}

		// Build the link element
		$el = new Html\Element('a');

		// Handle nofollow class
		$nofollow = false;
		if ($node->modifier && isset($node->modifier->classes['nofollow'])) {
			$nofollow = true;
			unset($node->modifier->classes['nofollow']);
		}

		// Apply modifier (title, class, id, style, etc.)
		$el->attrs['href'] = null; // Reserve position at front
		$node->modifier?->decorate($texy, $el);

		// Set the URL
		$el->attrs['href'] = $url;

		// rel="nofollow"
		if ($nofollow || ($texy->linkModule->forceNoFollow && str_contains($url, '//'))) {
			$el->attrs['rel'] = 'nofollow';
		}

		// Add content
		$el->children = $gen->renderNodes($node->content->children);

		return $el;
	},
);


// Process the text
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
