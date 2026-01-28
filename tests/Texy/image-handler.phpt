<?php declare(strict_types=1);

/**
 * Test: Images with custom handler.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


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


test('custom image handler', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->linkedRoot = '../images/big/';
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->addHandler('image', imageHandler(...));

	Assert::matchFile(
		__DIR__ . '/expected/image-handler.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/image-handler.texy')),
	);
});
