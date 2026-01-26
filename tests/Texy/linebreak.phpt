<?php declare(strict_types=1);

/**
 * Test: parse - Line breaks.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function process(string $text, bool $mergeLines = true): string
{
	$texy = new Texy\Texy;
	$texy->mergeLines = $mergeLines;
	return $texy->process($text);
}


test('mergeLines=true - line break with space', function () {
	// Line starting with space creates a line break
	Assert::match(
		<<<'HTML'
			<p>first line<br>
			second line</p>
			HTML,
		process(<<<'XX'
			first line
			 second line
			XX),
	);
});


test('mergeLines=true - no break without space', function () {
	// Line without leading space - no line break (lines are merged)
	Assert::match(
		<<<'HTML'
			<p>first line second line</p>
			HTML,
		process(<<<'XX'
			first line
			second line
			XX),
	);
});


test('mergeLines=false - every newline is break', function () {
	Assert::match(
		<<<'HTML'
			<p>first line<br>
			second line</p>
			HTML,
		process(<<<'XX'
			first line
			second line
			XX
			, mergeLines: false),
	);
});


test('multiple line breaks', function () {
	Assert::match(
		<<<'HTML'
			<p>line1<br>
			line2<br>
			line3</p>
			HTML,
		process(<<<'XX'
			line1
			 line2
			 line3
			XX),
	);
});


test('line break with inline formatting', function () {
	Assert::match(
		<<<'HTML'
			<p><strong>bold</strong> text<br>
			more text</p>
			HTML,
		process(<<<'XX'
			**bold** text
			 more text
			XX),
	);
});
