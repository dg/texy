<?php declare(strict_types=1);

/**
 * Test: Figure module.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with caption', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';

	Assert::matchFile(
		__DIR__ . '/expected/figure.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure with HTML5 figure tag', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'aside-left';
	$texy->imageModule->rightClass = 'aside-right';
	$texy->figureModule->tagName = 'figure';
	$texy->figureModule->class = null;

	Assert::matchFile(
		__DIR__ . '/expected/figure-html5.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});
