<?php declare(strict_types=1);

/**
 * Test: parse - Phrases (inline formatting).
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function process(string $text): string
{
	$texy = new Texy\Texy;
	return $texy->process($text);
}


test('strong phrase', function () {
	Assert::match(
		'<p><strong>bold</strong></p>',
		process('**bold**'),
	);
});


test('emphasis phrase', function () {
	Assert::match(
		'<p><em>italic</em></p>',
		process('//italic//'),
	);
});


test('code phrase', function () {
	Assert::match(
		'<p><code>code</code></p>',
		process('`code`'),
	);
});


test('strong+emphasis phrase', function () {
	Assert::match(
		'<p><strong><em>bold italic</em></strong></p>',
		process('***bold italic***'),
	);
});


test('nested phrases', function () {
	Assert::match(
		'<p><strong>bold and <em>italic</em> text</strong></p>',
		process('**bold and //italic// text**'),
	);
});


test('phrase with modifier', function () {
	Assert::match(
		'<p><strong class="highlight">bold</strong></p>',
		process('**bold .[highlight]**'),
	);
});


test('acronym with explanation', function () {
	Assert::match(
		'<p><abbr title="North Atlantic Treaty Organisation">NATO</abbr></p>',
		process('NATO((North Atlantic Treaty Organisation))'),
	);
});


test('quoted acronym', function () {
	Assert::match(
		'<p><abbr title="and others">et al.</abbr></p>',
		process('"et al."((and others))'),
	);
});
