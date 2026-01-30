<?php

/**
 * Test: link & images.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->htmlGenerator->linkRoot = 'xxx/';
	$texy->htmlGenerator->imageRoot = '../images/';
	$texy->htmlGenerator->imageLeftClass = 'left';
	$texy->htmlOutputModule->lineWrap = 180;
	return $texy;
}


test('links and images', function () {
	$texy = createTexy();
	Assert::matchFile(
		__DIR__ . '/expected/link-combined.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});


test('links and images in safe mode', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	$texy->htmlGenerator->allowedTags['a'][] = 'rel';
	Assert::matchFile(
		__DIR__ . '/expected/link-combined-safe.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});


test('links and images with allowed classes', function () {
	$texy = createTexy();
	$texy->allowedClasses = ['#nofollow'];
	$texy->allowedStyles = false;
	Assert::matchFile(
		__DIR__ . '/expected/link-combined-classes.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/link-combined.texy')),
	);
});
