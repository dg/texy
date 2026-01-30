<?php declare(strict_types=1);

/**
 * Test: Link reference definitions
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('link reference is resolved via phrase syntax', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com\">Click here</a></p>\n",
		$texy->process("\"Click here\":[link]\n\n[link]: https://example.com"),
	);
});


test('link reference with query string', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com?foo=bar\">Click</a></p>\n",
		$texy->process("\"Click\":[link?foo=bar]\n\n[link]: https://example.com"),
	);
});


test('link reference with fragment', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com#section\">Click</a></p>\n",
		$texy->process("\"Click\":[link#section]\n\n[link]: https://example.com"),
	);
});


test('undefined reference uses destination as URL', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"undefined\">Click</a></p>\n",
		$texy->process('"Click":[undefined]'),
	);
});


test('link definition is removed from output', function () {
	$texy = new Texy\Texy;
	$result = $texy->process("[link]: https://example.com\n\ntext");
	Assert::same("<p>text</p>\n", $result);
});


test('case insensitive reference matching', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><a href=\"https://example.com\">Click</a></p>\n",
		$texy->process("\"Click\":[LINK]\n\n[link]: https://example.com"),
	);
});


// =============================================================================
// User-defined definitions via addDefinition()
// =============================================================================

test('user-defined link definition works', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('texy', 'https://texy.nette.org/');
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\">Visit</a></p>\n",
		$texy->process('"Visit":[texy]'),
	);
});


test('user-defined definition persists across process() calls', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('texy', 'https://texy.nette.org/');

	// First process()
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\">Texy</a></p>\n",
		$texy->process('"Texy":[texy]'),
	);

	// Second process() - definition should still work
	Assert::same(
		"<p><a href=\"https://texy.nette.org/\">Texy</a></p>\n",
		$texy->process('"Texy":[texy]'),
	);
});


test('document-defined reference leaks to next process() [BUG]', function () {
	$texy = new Texy\Texy;

	// First process() defines a reference
	$texy->process("[link]: https://example.com\n\n\"Click\":[link]");

	// Second process() - reference should NOT be available, but it is (BUG)
	// This documents the current buggy behavior
	Assert::same(
		"<p><a href=\"https://example.com\">Click</a></p>\n",
		$texy->process('"Click":[link]'),
	);
});


test('user-defined definition is overwritten by document definition', function () {
	$texy = new Texy\Texy;
	$texy->linkModule->addDefinition('link', 'https://user-defined.com/');

	// Document defines same reference - it overwrites user-defined one
	Assert::same(
		"<p><a href=\"https://document.com\">Click</a></p>\n",
		$texy->process("\"Click\":[link]\n\n[link]: https://document.com"),
	);
});
