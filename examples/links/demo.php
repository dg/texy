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
 * - How to change the document in a transform pass
 * - How to find all links of the document
 * - How to detect relative vs. absolute URLs
 * - How to handle custom URL schemes
 *
 * Where a link points is the meaning of the document, not its look, so it is changed
 * in a pass rather than in a renderer: every output (HTML, plain text, Markdown) then
 * gets the new URL, and the renderer of links stays the default one.
 *
 * TEXY LINK SYNTAX:
 * "link text":url           - basic link
 * "link text":relative-page - relative link (transformed by our pass)
 * "link text":[wiki:topic]  - custom scheme (transformed to Wikipedia)
 */

use Texy\Helpers;
use Texy\Nodes\{DocumentNode, LinkNode};

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;


// The pass runs in transform(), after references like [wiki:topic] are resolved
$texy->addPass(function (DocumentNode $document): void {
	foreach ($document->find(LinkNode::class, fn(LinkNode $link) => $link->url !== null) as $link) {
		$link->url = rewriteUrl($link->url);
	}
});


function rewriteUrl(string $url): string
{
	if (str_starts_with($url, 'wiki:')) {
		// "wiki:texy" becomes a Wikipedia search link
		return 'https://en.wikipedia.org/wiki/Special:Search?search=' . urlencode(substr($url, 5));

	} elseif (Helpers::isRelative($url)) {
		// "my-page" becomes "index?page=my-page", the URL format of your CMS
		return 'index?page=' . urlencode($url);
	}

	return $url;
}


// Process the text
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->toHtml($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;


// Show the generated HTML source code
echo '<hr>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
