<?php declare(strict_types=1);

/**
 * Test: Complete syntax.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('complete syntax showcase', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
	$texy->allowed['phrase/ins'] = true;
	$texy->allowed['phrase/del'] = true;
	$texy->allowed['phrase/sup'] = true;
	$texy->allowed['phrase/sub'] = true;
	$texy->allowed['phrase/cite'] = true;
	$texy->typographyModule->locale = 'en';
	$texy->horizLineModule->classes['*'] = 'hidden';

	Assert::matchFile(
		__DIR__ . '/expected/showcase.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/showcase.texy')),
	);

	Assert::matchFile(
		__DIR__ . '/expected/showcase.txt',
		$texy->toText(),
	);
});
