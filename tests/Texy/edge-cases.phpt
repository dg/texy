<?php declare(strict_types=1);

/**
 * Test: Special cases.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('special cases and edge cases', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->allowed['longwords'] = false;
	$texy->typographyModule->locale = 'en';

	Assert::matchFile(
		__DIR__ . '/expected/edge-cases.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/edge-cases.texy')),
	);
});
