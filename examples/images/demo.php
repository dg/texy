<?php declare(strict_types=1);

/**
 * CUSTOMIZING IMAGE HANDLING
 *
 * This example shows how to take control of image processing.
 * This is especially useful for CMS systems where you want to:
 * - Map image names to actual file paths
 * - Add custom attributes to images
 * - Transform special image names (like "user" -> "profile.jpg")
 *
 * WHAT YOU'LL LEARN:
 * - How to define images by name, as a CMS would
 * - How to modify the HTML of images using an HTML handler
 * - How to set image folder paths
 * - How to add CSS classes for left/right floated images
 *
 * TEXY IMAGE SYNTAX:
 * [* image.gif *]           - simple image
 * [* image.gif *]:link.html - image as a link
 * [* image.gif *]::         - image linking to itself
 * [* image.gif <]           - left-floated image
 * [* image.gif >]           - right-floated image
 */

use Texy\Nodes\ImageNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// Configure image paths
$texy->htmlOutput->imageBaseUrl = 'imagesdir/';  // Base folder for images

// CSS classes for floated images
$texy->htmlOutput->floatClasses = [
	'left' => 'my-left-class',   // Class for [* ... <] (left-floated)
	'right' => 'my-right-class', // Class for [* ... >] (right-floated)
];


// The special keyword "user" stands for an actual image file with its alternative text,
// both in [* user *] and in the link of [* user *]::
$texy->config->addImageDefinition('user', 'image.gif', alt: 'Texy! logo');


// Register our custom image handler
// The first parameter type (ImageNode) determines which node class the handler processes
$texy->htmlOutput->addRenderer(
	function (ImageNode $node, Html\Renderer $gen, ?Closure $previous): Html\Element|string|null {
		// Call the previous handler (default or another custom one) to build the image
		$el = $previous ? $previous($node) : null;

		// and add an attribute to the <img> it has built
		if ($el instanceof Html\Element && $el->name === 'img') {
			$el->attrs['loading'] = 'lazy';
		}
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
