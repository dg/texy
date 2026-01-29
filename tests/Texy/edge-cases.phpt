<?php

/**
 * Test: Special cases.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('special cases and edge cases', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->allowed[Texy\Syntax::Hyphenation] = false;
	$texy->typographyModule->locale = 'en';

	Assert::matchFile(
		__DIR__ . '/expected/edge-cases.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/edge-cases.texy')),
	);
});
