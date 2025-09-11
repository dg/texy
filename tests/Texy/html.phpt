<?php declare(strict_types=1);

/**
 * Test: HTML tags.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->lineWrap = 180;
	return $texy;
}


test('HTML tags disabled', function () {
	$texy = createTexy();
	Texy\Configurator::safeMode($texy);
	$texy->htmlPolicy->allowedTags = Texy\Texy::NONE;
	Assert::matchFile(
		__DIR__ . '/expected/html-none.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/html.texy')),
	);
});


test('html-tags3', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags3.html',
		createTexy()->toHtml(file_get_contents(__DIR__ . '/sources/html-tags3.texy')),
	);
});


test('html-tags5', function () {
	Assert::matchFile(
		__DIR__ . '/expected/html-tags5.html',
		createTexy()->toHtml(file_get_contents(__DIR__ . '/sources/html-tags5.texy')),
	);
});
