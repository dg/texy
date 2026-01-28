<?php declare(strict_types=1);

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
 * - How to intercept and modify links using a handler
 * - How to detect relative vs. absolute URLs
 * - How to handle custom URL schemes
 * - How to get a list of all links in the document
 *
 * TEXY LINK SYNTAX:
 * "link text":url           - basic link
 * "link text":relative-page - relative link (transformed by our handler)
 * "link text":wiki:topic    - custom scheme (transformed to Wikipedia)
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * Custom handler for links
 *
 * This handler transforms links based on their URL:
 * - Relative links (like "page-name") become "index?page=page-name"
 * - Links starting with "wiki:" become Wikipedia search URLs
 */
function phraseHandler(Texy\HandlerInvocation $invocation, $phrase, $content, Texy\Modifier $modifier, ?Texy\Link $link = null): Texy\HtmlElement|string|null
{
	// Only process if there's actually a link
	if (!$link) {
		return $invocation->proceed();
	}

	// Check if this is a relative link (doesn't contain "://")
	if (Texy\Helpers::isRelative($link->URL)) {
		// Transform relative links to your CMS URL format
		// "my-page" becomes "index?page=my-page"
		$link->URL = 'index?page=' . urlencode($link->URL);

	} elseif (substr($link->URL, 0, 5) === 'wiki:') {
		// Handle our custom "wiki:" URL scheme
		// "wiki:texy" becomes a Wikipedia search link
		$searchTerm = substr($link->URL, 5);  // Remove "wiki:" prefix
		$link->URL = 'https://en.wikipedia.org/wiki/Special:Search?search=' . urlencode($searchTerm);
	}

	// Continue processing with the modified link
	return $invocation->proceed();
}


$texy = new Texy;

// Register our custom link handler
$texy->addHandler('phrase', 'phraseHandler');


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->process($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show all links found in the document
// This is useful for building sitemaps or checking for broken links
echo '<hr />';
echo '<pre>';
//print_r($texy->summary['links']);
echo '</pre>';


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
