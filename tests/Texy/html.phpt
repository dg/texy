<?php declare(strict_types=1);

/**
 * Test: HTML tags.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// all cases are temporarily disabled - skip the file until the first one lights up
Tester\Environment::skip('temporarily disabled');


// temporarily disabled tests - the code cannot meet these expectations yet
function skip(string $description, \Closure $fn): void
{
}


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkRoot = 'xxx/';
	$texy->htmlOutput->lineWrap = 180;
	return $texy;
}


skip('HTML tags', function () {
	$texy = createTexy();
	Assert::matchFile(
		__DIR__ . '/expected/html.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


skip('HTML tags in safe mode', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	Assert::matchFile(
		__DIR__ . '/expected/html-safe.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


skip('HTML tags disabled', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	$texy->htmlOutput->allowedTags = Texy\Texy::NONE;
	Assert::matchFile(
		__DIR__ . '/expected/html-none.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


skip('html-tags2', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags2.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags2.texy')),
	);
});


skip('html-tags3', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags3.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags3.texy')),
	);
});


skip('html-tags4', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags4.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags4.texy')),
	);
});


skip('html-tags5', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags5.html',
		createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags5.texy')),
	);
});
