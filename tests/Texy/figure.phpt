<?php declare(strict_types=1);

/**
 * Test: Figure module.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with caption', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses['left'] = 'left';

	Assert::matchFile(
		__DIR__ . '/expected/figure.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure with HTML5 figure tag', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses = ['left' => 'aside-left', 'right' => 'aside-right'];
	$texy->htmlOutput->figureTag = 'figure';
	$texy->htmlOutput->figureClass = null;

	Assert::matchFile(
		__DIR__ . '/expected/figure-html5.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});
