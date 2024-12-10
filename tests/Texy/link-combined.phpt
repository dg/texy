<?php declare(strict_types=1);

/**
 * Test: link & images.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->imageModule->leftClass = 'left';
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
	$texy->allowedTags['a'][] = 'rel';
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
