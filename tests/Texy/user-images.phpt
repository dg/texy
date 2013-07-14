<?php

/**
 * Test: Images.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->imageModule->root = '../images/';
$texy->imageModule->linkedRoot = '../images/big/';
$texy->htmlOutputModule->lineWrap = 180;

function imageHandler($invocation, $image, $link)
{
	$texy = $invocation->getTexy();
	if ($image->URL == 'user') { // accepts only [* user *]
		$image->URL = 'image.gif'; // image URL
		$image->modifier->title = 'Texy! logo';
		if ($link) {
			$link->URL = 'image-big.gif';
		}
	}

	return $invocation->proceed();
}

$texy->addHandler('image', 'imageHandler');

Assert::matchFile(
	__DIR__ . '/expected/user-images.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/user-images.texy'))
);
