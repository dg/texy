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
 * - How to intercept and modify images using a handler
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


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * Custom handler for images
 *
 * This handler intercepts the special image name "user" and transforms it
 * into an actual image file. This allows you to use shortcut names in your
 * Texy text that get expanded to real paths.
 */
function imageHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, ?Texy\Link $link = null): Texy\HtmlElement|string|null
{
	// Check if the image URL is our special keyword "user"
	if ($image->URL == 'user') {
		// Transform it to an actual image file
		$image->URL = 'image.gif';
		$image->modifier->title = 'Texy! logo';

		// If the image is a link, set the link destination too
		if ($link) {
			$link->URL = 'big.gif';
		}
	}

	// Let Texy continue with the (possibly modified) image
	return $invocation->proceed($image, $link);
}


$texy = new Texy;

// Register our custom image handler
$texy->addHandler('image', imageHandler(...));

// Configure image paths
$texy->imageModule->root = 'imagesdir/';           // Base folder for images
$texy->imageModule->linkedRoot = 'imagesdir/big/'; // Folder for linked/full-size images

// CSS classes for aligned images
$texy->imageModule->leftClass = 'my-left-class';   // Class for [*< ... *] (left-aligned)
$texy->imageModule->rightClass = 'my-right-class'; // Class for [*> ... *] (right-aligned)


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
