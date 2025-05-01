<?php declare(strict_types=1);

/**
 * BUILDING A COMMENT SYSTEM WITH REFERENCES
 *
 * This example shows how to use Texy to format user comments safely,
 * with support for referencing other comments using [1], [2], etc.
 *
 * WHAT YOU'LL LEARN:
 * - How to turn references (like [1], [2]) into mentions of other comments
 * - How to change the document in a transform pass, and the look of a node in a renderer
 * - How to use safeMode() for safe processing of user comments
 *
 * USE CASE:
 * In a forum or comment system, users can reference other comments:
 * "I agree with [1] but [2] is wrong."
 * This becomes links to #comm-1 and #comm-2 on the page.
 */

use Texy\Nodes\{ContentNode, DocumentNode, LinkNode};
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// Authors of the comments on the page, by their number
$names = [1 => 'Punkrats', 2 => 'Servats', 3 => 'Bonifats'];


// Where a mention points and what it says is the meaning of the document, so a pass sets it.
// A bare reference like [1] becomes a LinkNode whose $ref keeps the written name.
$texy->addHandler(function (DocumentNode $document) use ($names): void {
	foreach ($document->find(LinkNode::class, fn(LinkNode $link) => isset($names[$link->ref])) as $link) {
		$link->url = '#comm-' . $link->ref;  // links to <div id="comm-1">
		$link->content = ContentNode::fromText("[$link->ref] {$names[$link->ref]}:");
	}
});


// The class is the look of the link, so a renderer adds it. A class written in the modifier
// would be dropped in safe mode, which allows no classes from the text.
$texy->htmlOutput->addRenderer(
	function (LinkNode $node, Html\Renderer $renderer, Closure $previous): Html\Element|string {
		$el = $previous($node);
		if ($el instanceof Html\Element && str_starts_with((string) $node->url, '#comm-')) {
			$el->addClass('comment');
		}
		return $el;
	},
);

// IMPORTANT: Use safe mode for user-submitted content!
// This prevents XSS attacks by:
// - Allowing only safe HTML tags
// - Disabling images (which could be used for tracking)
// - Adding rel="nofollow" to links
Texy\Configurator::safeMode($texy);

// Bare [ref] references are opt-in
$texy->allowedSyntax[Texy\Syntax::LinkReference] = true;

// Alternative: If you want to disable specific features manually:
// $texy->allowedSyntax[Texy\Syntax::Image] = false;
// $texy->allowedSyntax[Texy\Syntax::Figure] = false;
// $texy->allowedSyntax[Texy\Syntax::AutolinkUrl] = false;


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
