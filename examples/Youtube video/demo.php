<?php

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

declare(strict_types=1);

use Texy\Nodes\ImageNode;
use Texy\Output\Html;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;


// Register our custom image handler
// This works for both inline images AND images in figures,
// because FigureModule delegates to the generator for ImageNode processing.
$texy->htmlGenerator->registerHandler(
	function (ImageNode $node, Html\Generator $gen, ?Closure $previous) use ($texy): Html\Element|string|null {
		// Check if the URL has our custom "youtube:" scheme
		$url = $node->url ?? '';
		if (!str_starts_with($url, 'youtube:')) {
			// Not a YouTube video, let default handler process it
			return $previous ? $previous($node, $gen) : null;
		}

		$videoId = substr($url, 8); // Remove "youtube:" prefix
		$width = $node->width ?: 425;
		$height = $node->height ?: 350;

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
		return $gen->protect($code, $gen::ContentBlock);
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
