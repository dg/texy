<?php

/**
 * This demo shows how Texy! control images (useful for CMS)
 *     - programmable images controlling
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
	if ($image->URL == 'user') { // accepts only [* user *]
		$image->URL = 'image.gif'; // image URL
		$image->modifier->title = 'Texy! logo';
		if ($link) { // linked image
			$link->URL = 'big.gif';
		}
	}

	return $invocation->proceed($image, $link);
}


$texy = new Texy;
$texy->addHandler('image', 'imageHandler');
$texy->imageModule->root = 'imagesdir/';       // "in-line" images root
$texy->imageModule->linkedRoot = 'imagesdir/big/';   // "linked" images root
$texy->imageModule->leftClass = 'my-left-class';    // left-floated image modifier
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
echo htmlspecialchars($html);
echo '</pre>';


// echo all used images
echo '<hr />';
echo '<pre>';
echo 'used images:';
print_r($texy->summary['images']);
echo '</pre>';
