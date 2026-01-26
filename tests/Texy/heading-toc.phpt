<?php declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('title property - first heading', function () {
	$texy = new Texy\Texy;
	$texy->process("First Heading\n=============\n\nSecond Heading\n--------------");

	Assert::same('First Heading', $texy->headingModule->title);
});


test('title property - no headings', function () {
	$texy = new Texy\Texy;
	$texy->process('Just a paragraph.');

	Assert::null($texy->headingModule->title);
});


test('TOC structure - underlined headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->process("Title\n=====\n\nSubtitle\n--------");

	Assert::count(2, $texy->headingModule->TOC);

	// First heading
	Assert::same('Title', $texy->headingModule->TOC[0]['title']);
	Assert::same(1, $texy->headingModule->TOC[0]['level']);
	Assert::same('underlined', $texy->headingModule->TOC[0]['type']);

	// Second heading
	Assert::same('Subtitle', $texy->headingModule->TOC[1]['title']);
	Assert::same(2, $texy->headingModule->TOC[1]['level']);
	Assert::same('underlined', $texy->headingModule->TOC[1]['type']);
});


test('TOC structure - surrounded headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->process("### Title ###\n\n## Subtitle ##");

	Assert::count(2, $texy->headingModule->TOC);

	// First heading (### = h1, more # = higher level)
	Assert::same('Title', $texy->headingModule->TOC[0]['title']);
	Assert::same(1, $texy->headingModule->TOC[0]['level']);
	Assert::same('surrounded', $texy->headingModule->TOC[0]['type']);

	// Second heading (## = h2)
	Assert::same('Subtitle', $texy->headingModule->TOC[1]['title']);
	Assert::same(2, $texy->headingModule->TOC[1]['level']);
	Assert::same('surrounded', $texy->headingModule->TOC[1]['type']);
});


test('generateID = false (default)', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("Title\n=====");

	Assert::notContains('id=', $html);
});


test('generateID = true', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$html = $texy->process("Title\n=====");

	Assert::contains('id="toc-title"', $html);
});


test('custom idPrefix', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->headingModule->idPrefix = 'h-';
	$html = $texy->process("Title\n=====");

	Assert::contains('id="h-title"', $html);
});


test('ID collision - duplicate headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$html = $texy->process("Title\n=====\n\nTitle\n=====\n\nTitle\n=====");

	Assert::contains('id="toc-title"', $html);
	Assert::contains('id="toc-title-2"', $html);
	Assert::contains('id="toc-title-3"', $html);
});
