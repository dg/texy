<?php declare(strict_types=1);

/**
 * Test: parse - HTML tags and comments.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function process(string $text): string
{
	$texy = new Texy\Texy;
	$texy->allowedTags = Texy\Texy::ALL;
	return $texy->process($text);
}


test('opening HTML tag', function () {
	Assert::match(
		'<p><span>text</span></p>',
		process('<span>text</span>'),
	);
});


test('self-closing HTML tag', function () {
	Assert::match(
		<<<'HTML'
			<p>before<br>
			after</p>
			HTML,
		process('before<br/>after'),
	);
});


test('HTML tag with attributes', function () {
	Assert::match(
		'<p><a href="http://example.com" title="Example">link</a></p>',
		process('<a href="http://example.com" title="Example">link</a>'),
	);
});


test('HTML tag with class', function () {
	Assert::match(
		'<p><span class="highlight">text</span></p>',
		process('<span class="highlight">text</span>'),
	);
});


test('nested HTML tags', function () {
	// Block-level HTML like <div> doesn't create empty paragraph wrapper
	Assert::match(
		'<div><span>nested</span></div>',
		process('<div><span>nested</span></div>'),
	);
});


test('HTML comment', function () {
	Assert::match(
		'<p>before<!-- comment -->after</p>',
		process('before<!-- comment -->after'),
	);
});


test('HTML comment with dashes sanitized', function () {
	// HTML comment on its own doesn't get p wrapper
	Assert::match(
		'<!-- a - b -->',
		process('<!-- a--b -->'),
	);
});


test('orphan closing tag - ignored by well-forming', function () {
	Assert::match(
		'text',
		process('text</div>'),
	);
});
