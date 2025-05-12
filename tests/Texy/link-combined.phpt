<?php declare(strict_types=1);

/**
 * Test: link & images.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->floatClasses['left'] = 'left';
	$texy->htmlOutput->lineWrap = 180;
	return $texy;
}


test('links and images', function () {
	$texy = createTexy();
	Assert::matchFile(
		__DIR__ . '/expected/link-combined.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});


test('links and images in safe mode', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	$texy->htmlPolicy->allowedTags['a'][] = 'rel';
	Assert::matchFile(
		__DIR__ . '/expected/link-combined-safe.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});


test('links and images with allowed classes', function () {
	$texy = createTexy();
	$texy->htmlPolicy->allowedClasses = ['#nofollow'];
	$texy->htmlPolicy->allowedStyles = false;
	Assert::matchFile(
		__DIR__ . '/expected/link-combined-classes.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});
