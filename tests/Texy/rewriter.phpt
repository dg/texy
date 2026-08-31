<?php declare(strict_types=1);

/**
 * Test: Rewriter writes into the source by ranges.
 */

use Tester\Assert;
use Texy\{Range, RewriteException, Rewriter, SourcePosition};

require __DIR__ . '/../bootstrap.php';


test('replacements are applied at once, by the original offsets', function () {
	$rewriter = new Rewriter('one two three');
	$rewriter->replace(new Range(8, 5), 'THREE');
	$rewriter->replace(new Range(0, 3), '1');
	Assert::same(2, $rewriter->count());
	Assert::same('1 two THREE', $rewriter->getResult());
});


test('adjacent replacements, at the end of the source, and an empty one', function () {
	$rewriter = new Rewriter('abc');
	$rewriter->replace(new Range(0, 1), 'A');
	$rewriter->replace(new Range(1, 1), '');
	$rewriter->replace(new Range(2, 1), 'C');
	$rewriter->replace(new Range(3, 0), '!');
	Assert::same('AC!', $rewriter->getResult());
});


test('insertions at the same place keep their order and go before a replacement starting there', function () {
	$rewriter = new Rewriter('abc');
	$rewriter->replace(new Range(1, 1), 'B');
	$rewriter->replace(new Range(1, 0), '1');
	$rewriter->replace(new Range(1, 0), '2');
	Assert::same('a12Bc', $rewriter->getResult());
});


test('a replacement that changes nothing is not recorded', function () {
	$rewriter = new Rewriter('abc');
	$rewriter->replace(new Range(0, 1), 'a');
	Assert::same(0, $rewriter->count());
	Assert::same('abc', $rewriter->getResult());
});


test('overlapping replacements are an error', function () {
	$rewriter = new Rewriter("first\nsecond");
	$rewriter->replace(new Range(6, 4), 'x');
	Assert::exception(
		fn() => $rewriter->replace(new Range(8, 4), 'y'),
		RewriteException::class,
		"Line 2, column 3: the replacement of 'cond' overlaps another one.",
	);
});


test('a range that does not hold the expected text is an error', function () {
	$rewriter = new Rewriter("first\nžluť");
	Assert::exception(
		fn() => $rewriter->replace(new Range(6, 4), 'x', 'luť'),
		RewriteException::class,
		"Line 2, column 1: expected 'luť', found 'žlu'.",
	);
	$rewriter->replace(new Range(8, 4), 'x', 'luť');
	Assert::same("first\nžx", $rewriter->getResult());
});


test('a position counts lines and characters from 1', function () {
	Assert::equal(new SourcePosition(1, 1), SourcePosition::fromOffset('', 0));
	Assert::equal(new SourcePosition(2, 3), SourcePosition::fromOffset("a\nžx", 5));
});
