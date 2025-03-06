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

use Texy\Nodes\ImageNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;


// Register our custom image handler
// This works for both inline images AND images in figures,
// because a figure renders its ImageNode through the same renderer.
$texy->htmlOutput->addRenderer(
	function (ImageNode $node): ?Html\Element {
		// Check if the URL has our custom "youtube:" scheme
		if (!str_starts_with((string) $node->url, 'youtube:')) {
			return null; // not a YouTube video, the default renderer takes it
		}

		// the element escapes its attributes itself
		return new Html\Element('iframe', [
			'width' => $node->width ?? 425,
			'height' => $node->height ?? 350,
			'src' => 'https://www.youtube.com/embed/' . substr((string) $node->url, 8),
			'allow' => 'autoplay',
			'allowfullscreen' => true,
		]);
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
