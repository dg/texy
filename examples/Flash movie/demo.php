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
	$texy = $invocation->getTexy();

	if (substr($image->URL, -4) === '.swf')  // accepts only *.swf
	{
		$movie = Texy::prependRoot($image->URL, $texy->imageModule->root);

		$dimensions =
			($image->width ? 'width="'.$image->width.'" ' : '')
			. ($image->height ? 'width="'.$image->height.'" ' : '');

		$movie = htmlSpecialChars($movie);
		$altContent = htmlSpecialChars($image->modifier->title);

		// @see http://phpfashion.com/how-to-correctly-insert-a-flash-into-xhtml
		$code = '
<!--[if !IE]> -->
<object type="application/x-shockwave-flash" data="'.$movie.'" '.$dimensions.'>
<!-- <![endif]-->

<!--[if IE]>
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" '.$dimensions.'
codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=5,0,0,0">
<param name="movie" value="'.$movie.'" />
<!--><!--dgx-->

	<p>'.$altContent.'</p>
</object>
<!-- <![endif]-->
';
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
