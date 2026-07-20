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


test('paired phrases keep their delimiter guards', function () {
	// the guards encoded in the phrase table: each delimiter must not touch
	// its own character, and // must not eat the one in http://
	Assert::match('<p>2 * 3 * 4</p>', process('2 * 3 * 4'));
	Assert::match('<p><a href="http://x.cz">http://x.cz</a></p>', process('http://x.cz'));
});


test('asymmetric >>quote<< delimiters', function () {
	Assert::match('<p><q>quoted</q></p>', process('>>quoted<<'));
});


test('deleted phrase and its arrow guard', function () {
	$texy = new Texy;
	$texy->allowed[Texy\Syntax::Deleted] = true;
	Assert::match('<p><del>gone</del></p>', trim($texy->process('--gone--')));
	// the guardAfter keeps --> an arrow instead of an unterminated deletion
	Assert::contains("\u{2192}", $texy->process('x --> y'));
});


test('phrases can be extended through the table', function () {
	// the pattern of every paired phrase is generated, so a syntax that is
	// off by default behaves exactly like the built-in ones once enabled
	$texy = new Texy;
	$texy->allowed[Texy\Syntax::Superscript] = true;
	$texy->allowed[Texy\Syntax::Subscript] = true;
	Assert::match('<p>a<sup>b</sup> c<sub>d</sub></p>', trim($texy->process('a^^b^^ c__d__')));
});
