<?php declare(strict_types=1);

/**
 * Test: Figure module.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('figure with caption in a div', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses['left'] = 'left';
	$texy->htmlOutput->figureTag = 'div';
	$texy->htmlOutput->figureClass = 'figure';

	Assert::matchFile(
		__DIR__ . '/expected/figure.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});


test('figure with the default figure tag', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 180;
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses = ['left' => 'aside-left', 'right' => 'aside-right'];

	Assert::matchFile(
		__DIR__ . '/expected/figure-html5.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/figure.texy')),
	);
});
