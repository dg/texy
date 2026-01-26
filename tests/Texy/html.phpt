<?php declare(strict_types=1);

/**
 * Test: HTML tags.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->htmlOutputModule->lineWrap = 180;
	return $texy;
}


test('HTML tags', function () {
	$texy = createTexy();
	Assert::matchFile(
		__DIR__ . '/expected/html.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


test('HTML tags in safe mode', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	Assert::matchFile(
		__DIR__ . '/expected/html-safe.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


test('HTML tags disabled', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	$texy->allowedTags = $texy::NONE;
	Assert::matchFile(
		__DIR__ . '/expected/html-none.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


test('html-tags2', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags2.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags2.texy')),
	);
});


test('html-tags3', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags3.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags3.texy')),
	);
});


test('html-tags4', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags4.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags4.texy')),
	);
});


test('html-tags5', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags5.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags5.texy')),
	);
});
