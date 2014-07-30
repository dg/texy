<?php

/**
 * Test: Images.
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->imageModule->root = '../images/';
$texy->imageModule->linkedRoot = '../images/big/';
$texy->htmlOutputModule->lineWrap = 180;

function imageHandler($invocation, $image, $link)
{
    $texy = $invocation->getTexy();
    if ($image->URL == 'user')  // accepts only [* user *]
    {
        $image->URL = 'image.gif'; // image URL
        $image->modifier->title = 'Texy! logo';
        if ($link) $link->URL = 'image-big.gif'; // linked image
    }

    return $invocation->proceed();
}

$texy->addHandler('image', 'imageHandler');

Assert::matchFile(
	__DIR__ . '/expected/user-images.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/user-images.texy'))
);
