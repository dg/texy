<?php declare(strict_types=1);

/**
 * Test: HTML entity references (html/entity syntax).
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('entity references are decoded and re-escaped on output', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>a &amp; b &lt;c&gt; A\u{A0}B</p>\n",
		$texy->process('a &amp; b &lt;c&gt; &#65; &#x42;'),
	);
});


test('unknown or malformed references stay literal', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>&amp;bogus17; &amp;copy tady &amp; tam</p>\n",
		$texy->process('&bogus17; &copy tady & tam'),
	);
});


test('disabled syntax leaves entities as plain text', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::HtmlEntity] = false;
	Assert::same(
		"<p>a &amp;amp; b</p>\n",
		$texy->process('a &amp; b'),
	);
});


test('control characters smuggled in entities are stripped', function () {
	$texy = new Texy\Texy;
	// &#12; is the only C0 reference html_entity_decode resolves into the stripped range
	Assert::same("<p>ab</p>\n", $texy->process('a&#12;b'));
	// most control references are not recognized at all and stay literal
	Assert::same("<p>a&amp;#1;b</p>\n", $texy->process('a&#1;b'));
});


test('NUL reference becomes the replacement character', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>a\u{FFFD}b</p>\n",
		$texy->process('a&#0;b'),
	);
});


test('typography pairs quotes across entity boundaries', function () {
	$texy = new Texy\Texy;
	Assert::contains(
		'„slovo“',
		$texy->process('rekl &quot;slovo&quot; a sel'),
	);
});


test('code content is not touched by the entity syntax', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><code>&amp;amp;</code></p>\n",
		$texy->process('`&amp;`'),
	);
});


test('an entity node carries the exact source range', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('x &amp; y');
	$paragraph = $doc->content->children[0];
	$entity = $paragraph->content->children[1];
	Assert::same('&', $entity->text);
	Assert::same(2, $entity->range->offset);
	Assert::same(5, $entity->range->length);
});
