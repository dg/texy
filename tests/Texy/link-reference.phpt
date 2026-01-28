<?php declare(strict_types=1);

/**
 * Test: Reference resolution
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('link reference is resolved', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com\">Click here</a></p>\n",
		$texy->process("[link]\n\n[link]: https://example.com Click here"),
	);
});


test('link reference with label', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com\">Click here</a></p>\n",
		$texy->process("[link]\n\n[link]: https://example.com Click here"),
	);
});


test('link reference without label uses identifier', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com\">https://example.com</a></p>\n",
		$texy->process("[link]\n\n[link]: https://example.com"),
	);
});


test('link reference with query string', function () {
	$texy = new Texy\Texy;
	// When using [ref?query], the URL is used as label
	Assert::same(
		"<p><a href=\"https://example.com?foo=bar\">https://example.com</a></p>\n",
		$texy->process("[link?foo=bar]\n\n[link]: https://example.com"),
	);
});


test('link reference with fragment', function () {
	$texy = new Texy\Texy;
	// When using [ref#fragment], the URL is used as label
	Assert::same(
		"<p><a href=\"https://example.com#section\">https://example.com</a></p>\n",
		$texy->process("[link#section]\n\n[link]: https://example.com"),
	);
});


test('undefined reference stays as is', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>[undefined]</p>\n",
		$texy->process('[undefined]'),
	);
});


test('link definition is removed from output', function () {
	$texy = new Texy\Texy;
	$result = $texy->process("[link]: https://example.com\n\ntext");
	Assert::same("<p>text</p>\n", $result);
});


test('case insensitive reference matching', function () {
	$texy = new Texy\Texy;
	// Reference matching is case-insensitive, URL is used as label
	Assert::same(
		"<p><a href=\"https://example.com\">https://example.com</a></p>\n",
		$texy->process("[LINK]\n\n[link]: https://example.com"),
	);
});


// =============================================================================
// User-defined definitions via addDefinition()
// =============================================================================

test('user-defined link definition works', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('texy', 'https://texy.nette.org/', 'Texy!', 'The best converter');
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\" title=\"The best converter\">Texy!</a></p>\n",
		$texy->process('[texy]'),
	);
});


test('user-defined definition persists across process() calls', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('texy', 'https://texy.nette.org/', 'Texy!');

	// First process()
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\">Texy!</a></p>\n",
		$texy->process('[texy]'),
	);

	// Second process() - definition should still work
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\">Texy!</a></p>\n",
		$texy->process('[texy]'),
	);
});


test('document-defined reference leaks to next process() [BUG]', function () {
	$texy = new Texy\Texy;

	// First process() defines a reference
	$texy->process("[link]: https://example.com Click here\n\n[link]");

	// Second process() - reference should NOT be available, but it is (BUG)
	// This documents the current buggy behavior
	Assert::same(
		"<p><a href=\"https://example.com\">Click here</a></p>\n",
		$texy->process('[link]'),
	);
});


test('user-defined definition is overwritten by document definition', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('link', 'https://user-defined.com/', 'User Label');

	// Document defines same reference - it overwrites user-defined one
	Assert::same(
		"<p><a href=\"https://document.com\">Document Label</a></p>\n",
		$texy->process("[link]\n\n[link]: https://document.com Document Label"),
	);
});
