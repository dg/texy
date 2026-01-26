<?php declare(strict_types=1);

/**
 * Test: parse - Blockquote.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('blockquote with merged lines', function () {
	$texy = new Texy\Texy;

	// With mergeLines=true (default), newlines are merged into spaces
	Assert::match(
		<<<'HTML'
			<blockquote>
				<p>First line Second line</p>
			</blockquote>
			HTML,
		$texy->process(<<<'XX'
			> First line
			> Second line
			XX),
	);
});


test('blockquote with line breaks', function () {
	$texy = new Texy\Texy;

	// Line break occurs when next line starts with space
	Assert::match(
		<<<'HTML'
			<blockquote>
				<p>First line<br>
				Second line</p>
			</blockquote>
			HTML,
		$texy->process(<<<'XX'
			> First line
			>  Second line
			XX),
	);
});


test('blockquote single line', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<blockquote>
				<p>Quote text</p>
			</blockquote>
			HTML,
		$texy->process('> Quote text'),
	);
});
