<?php declare(strict_types=1);

/**
 * EMBEDDING YOUTUBE VIDEOS
 *
 * This example shows how to use Texy's image syntax to embed YouTube videos.
 * Instead of creating an <img> tag, we create an <iframe> for YouTube.
 *
 * WHAT YOU'LL LEARN:
 * - How to use image syntax with custom URL schemes
 * - How to transform special URLs into embedded iframes
 * - How to use image width/height for video dimensions
 *
 * SYNTAX:
 * [* youtube:VIDEO_ID *]             - embed with default size (425x350)
 * [* youtube:VIDEO_ID 640x360 *]     - embed with custom size
 *
 * EXAMPLE:
 * [* youtube:dQw4w9WgXcQ *]
 * This embeds the video https://www.youtube.com/watch?v=dQw4w9WgXcQ
 *
 * You can extend this pattern for other services like Vimeo, Spotify, etc.
 */


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * Custom handler for images
 *
 * Checks if the image URL uses our custom "youtube:" scheme.
 * If so, creates a YouTube embed iframe instead of an <img> tag.
 */
function imageHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, ?Texy\Link $link = null): Texy\HtmlElement|string|null
{
	// Check if the URL has a custom scheme (like "youtube:VIDEO_ID")
	$parts = explode(':', $image->URL);
	if (count($parts) !== 2) {
		// Not a custom scheme, let Texy handle it normally
		return $invocation->proceed();
	}

	$scheme = $parts[0];
	$videoId = $parts[1];

	// Handle YouTube videos
	if ($scheme === 'youtube') {
		// Use the image dimensions for the video size, or defaults
		$width = $image->width ?: 425;
		$height = $image->height ?: 350;

		// Create the YouTube embed iframe
		$code = '<iframe'
			. ' width="' . $width . '"'
			. ' height="' . $height . '"'
			. ' src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '"'
			. ' frameborder="0"'
			. ' allow="autoplay"'
			. ' allowfullscreen>'
			. '</iframe>';

		// Tell Texy this is ready-to-use HTML (don't process it further)
		$texy = $invocation->getTexy();
		return $texy->protect($code, $texy::CONTENT_BLOCK);
	}

	// Unknown scheme, let Texy handle it normally
	return $invocation->proceed();
}


$texy = new Texy;

// Register our custom image handler
$texy->addHandler('image', 'imageHandler');


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
