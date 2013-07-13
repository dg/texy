<?php

/**
 * TEXY! SUPPORT FOR FLASH
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


/**
 * User handler for images
 *
 * @param TexyHandlerInvocation  handler invocation
 * @param TexyImage
 * @param TexyLink
 * @return TexyHtml|string|FALSE
 */
function imageHandler($invocation, $image, $link)
{
	$parts = explode(':', $image->URL);
	if (count($parts) !== 2) return $invocation->proceed();

	switch ($parts[0]) {
	case 'youtube':
		$video = htmlSpecialChars($parts[1]);
		$dimensions = 'width="'.($image->width ? $image->width : 425).'" height="'.($image->height ? $image->height : 350).'"';
		$code = '<div><object '.$dimensions.'>'
			. '<param name="movie" value="http://www.youtube.com/v/'.$video.'" /><param name="wmode" value="transparent" />'
			. '<embed src="http://www.youtube.com/v/'.$video.'" type="application/x-shockwave-flash" wmode="transparent" '.$dimensions.' /></object></div>';

		$texy = $invocation->getTexy();
		return $texy->protect($code, Texy::CONTENT_BLOCK);
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
echo htmlSpecialChars($html);
echo '</pre>';
