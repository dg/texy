<?php declare(strict_types=1);

/**
 * Test: Figure module.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with caption', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';

	Assert::matchFile(
		__DIR__ . '/expected/figure.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure without required caption', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
	$texy->figureModule->requireCaption = false;

	Assert::matchFile(
		__DIR__ . '/expected/figure-nocaption.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure with HTML5 figure tag', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
	$texy->figureModule->requireCaption = false;
	$texy->figureModule->tagName = 'figure';
	$texy->figureModule->class = null;
	$texy->figureModule->leftClass = 'aside-left';
	$texy->figureModule->rightClass = 'aside-right';

	Assert::matchFile(
		__DIR__ . '/expected/figure-html5.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});
