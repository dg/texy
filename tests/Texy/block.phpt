<?php declare(strict_types=1);

/**
 * Test: Blocks
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nested div blocks', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->htmlOutputModule->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/block.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/block.texy')),
	);
});


test('block type variants', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->htmlOutputModule->lineWrap = 180;

	$texy->linkModule->addDefinition('texy', 'https://texy.nette.org/');

	Assert::matchFile(
		__DIR__ . '/expected/block-types.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/block-types.texy')),
	);
});
