<?php

/**
 * Test: Position tracking for AST nodes.
 *
 * Tests verify that all AST nodes have correct position (offset, length)
 * by comparing full AST dump output with expected files.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../AstDumper.php';


test('headings position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-heading.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-heading.txt',
		AstDumper::dump($doc),
	);
});


test('inline formatting position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-inline.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-inline.txt',
		AstDumper::dump($doc),
	);
});


test('lists position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-list.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-list.txt',
		AstDumper::dump($doc),
	);
});


test('blocks position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-block.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-block.txt',
		AstDumper::dump($doc),
	);
});


test('table position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-table.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-table.txt',
		AstDumper::dump($doc),
	);
});


test('images and links position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-image-link.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-image-link.txt',
		AstDumper::dump($doc),
	);
});


test('UTF-8 positions are byte-based', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-utf8.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-utf8.txt',
		AstDumper::dump($doc),
	);
});


test('nested structures position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-nested.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-nested.txt',
		AstDumper::dump($doc),
	);
});
