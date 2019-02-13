<?php

/**
 * TEXY! SUPPORT FOR FLASH
 */

declare(strict_types=1);


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


/**
 * User handler for images
 * @return Texy\HtmlElement|string|null
 */
function imageHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, Texy\Link $link = null)
{
	$parts = explode(':', $image->URL);
	if (count($parts) !== 2) {
		return $invocation->proceed();
	}

	switch ($parts[0]) {
	case 'youtube':
		$code = '<iframe width="' . ($image->width ?: 425) . '" height="' . ($image->height ?: 350) . '" '
			. 'src="https://www.youtube.com/embed/' . htmlspecialchars($parts[1]) . '" frameborder="0" allow="autoplay" allowfullscreen></iframe>';

		$texy = $invocation->getTexy();
		return $texy->protect($code, $texy::CONTENT_BLOCK);
	}

	return $invocation->proceed();
}


$texy = new Texy;
$texy->addHandler('image', 'imageHandler');


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;


// echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
