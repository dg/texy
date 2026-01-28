<?php

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
 * - How to intercept and modify images using an HTML handler
 * - How to set image folder paths
 * - How to add CSS classes for left/right aligned images
 * - How to set a default alt text
 * - How to get a list of all images used in the document
 *
 * TEXY IMAGE SYNTAX:
 * [* image.gif *]           - simple image
 * [* image.gif *]:link.html - image as a link
 * [*< image.gif *]          - left-aligned image
 * [*> image.gif *]          - right-aligned image
 */

declare(strict_types=1);

use Texy\Nodes\ImageNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// Configure image paths
$texy->imageModule->root = 'imagesdir/';  // Base folder for images

// CSS classes for aligned images
$texy->imageModule->leftClass = 'my-left-class';   // Class for [*< ... *] (left-aligned)
$texy->imageModule->rightClass = 'my-right-class'; // Class for [*> ... *] (right-aligned)


// Register our custom image handler
// The first parameter type (ImageNode) determines which node class the handler processes
$texy->htmlGenerator->registerHandler(
	function (ImageNode $node, Html\Generator $gen, ?Closure $previous): Html\Element|string|null {
		// Check if the image URL is our special keyword "user"
		if ($node->url === 'user') {
			// Transform it to an actual image file
			$node->url = 'image.gif';
			if ($node->modifier === null) {
				$node->modifier = new Texy\Modifier;
			}
			$node->modifier->title = 'Texy! logo';
		}

		// Call the previous handler (default or another custom one) to build the image
		return $previous ? $previous($node, $gen) : null;
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
