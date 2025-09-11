<?php declare(strict_types=1);

/**
 * Test: Blocks
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nested div blocks', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/block.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/block.texy')),
	);
});


test('block type variants', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->lineWrap = 180;

	$texy->config->addLinkDefinition('texy', 'https://texy.deegee.dev/');

	Assert::matchFile(
		__DIR__ . '/expected/block-types.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/block-types.texy')),
	);
});
