<?php

/**
 * Test: Figure module.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with caption', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->htmlGenerator->imageRoot = '../images/';
	$texy->htmlGenerator->imageLeftClass = 'left';

	Assert::matchFile(
		__DIR__ . '/expected/figure.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure with HTML5 figure tag', function () {
	$texy = new Texy\Texy;
	$texy->tabWidth = 0;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->htmlGenerator->imageRoot = '../images/';
	$texy->htmlGenerator->imageLeftClass = 'left';
	$texy->htmlGenerator->figureTagName = 'figure';
	$texy->htmlGenerator->figureClass = null;
	$texy->htmlGenerator->figureLeftClass = 'aside-left';
	$texy->htmlGenerator->figureRightClass = 'aside-right';

	Assert::matchFile(
		__DIR__ . '/expected/figure-html5.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});
