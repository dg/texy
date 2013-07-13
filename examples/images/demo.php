<?php

/**
 * This demo shows how Texy! control images (useful for CMS)
 *     - programmable images controlling
 *     - onMouseOver state
 *     - support for preloading
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
	if ($image->URL == 'user')  // accepts only [* user *]
	{
		$image->URL = 'image.gif'; // image URL
		$image->overURL = 'image-over.gif'; // onmouseover image
		$image->modifier->title = 'Texy! logo';
		if ($link) $link->URL = 'big.gif'; // linked image
	}

	return $invocation->proceed($image, $link);
}


$texy = new Texy();
$texy->addHandler('image', 'imageHandler');
$texy->imageModule->root       = 'imagesdir/';       // "in-line" images root
$texy->imageModule->linkedRoot = 'imagesdir/big/';   // "linked" images root
$texy->imageModule->leftClass  = 'my-left-class';    // left-floated image modifier
$texy->imageModule->rightClass = 'my-right-class';   // right-floated image modifier
$texy->imageModule->defaultAlt = 'default alt. text';// default image alternative text


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


// echo all used images
echo '<hr />';
echo '<pre>';
echo 'used images:';
print_r($texy->summary['images']);
echo 'onmouseover images:';
print_r($texy->summary['preload']);
echo '</pre>';
