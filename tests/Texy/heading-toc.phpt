<?php declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('generateID = false (default)', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("Title\n=====");

	Assert::notContains('id=', $html);
});


test('generateID = true', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$html = $texy->process("Title\n=====");

	Assert::contains('id="toc-title"', $html);
});


test('custom idPrefix', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$texy->config->headingIdPrefix = 'h-';
	$html = $texy->process("Title\n=====");

	Assert::contains('id="h-title"', $html);
});


test('ID collision - duplicate headings', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$html = $texy->process("Title\n=====\n\nTitle\n=====\n\nTitle\n=====");

	Assert::contains('id="toc-title"', $html);
	Assert::contains('id="toc-title-2"', $html);
	Assert::contains('id="toc-title-3"', $html);
});
