<?php

/**
 * TEXY! SUPPORT FOR FLASH
 */


// include Texy!
require_once __DIR__ . '/../../src/texy.php';


/**
 * User handler for images
 * @return Texy\HtmlElement|string|false
 */
function imageHandler(Texy\HandlerInvocation $invocation, Texy\Image $image, Texy\Link $link = null)
{
	$parts = explode(':', $image->URL);
	if (count($parts) !== 2) {
		return $invocation->proceed();
	}

	switch ($parts[0]) {
	case 'youtube':
		$video = htmlspecialchars($parts[1]);
		$dimensions = 'width="' . ($image->width ? $image->width : 425) . '" height="' . ($image->height ? $image->height : 350) . '"';
		$code = '<div><object ' . $dimensions . '>'
			. '<param name="movie" value="https://www.youtube.com/v/' . $video . '" /><param name="wmode" value="transparent" />'
			. '<embed src="https://www.youtube.com/v/' . $video . '" type="application/x-shockwave-flash" wmode="transparent" ' . $dimensions . ' /></object></div>';

		$texy = $invocation->getTexy();
		return $texy->protect($code, $texy::CONTENT_BLOCK);
	}

	return $invocation->proceed();
}


$texy = new Texy();
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
