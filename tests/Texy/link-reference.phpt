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


test('document-defined reference does NOT leak to next process()', function () {
	$texy = new Texy\Texy;

	// First process() defines a reference
	$texy->process("[link]: https://example.com\n\n\"Click\":[link]");

	// Second process() - reference should NOT be available, falls back to using "link" as URL
	Assert::same(
		"<p><a href=\"link\">Click</a></p>\n",
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


// =============================================================================
// Bare [reference] links (opt-in)
// =============================================================================

test('bare reference syntax is off by default', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>viz [home] stranka</p>\n",
		$texy->process('viz [home] stranka'),
	);
});


test('bare reference resolves against definitions when enabled', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::LinkReference] = true;
	Assert::same(
		"<p>viz <a href=\"https://example.com\">home</a> stranka</p>\n",
		$texy->process("viz [home] stranka\n\n[home]: https://example.com"),
	);
});


test('unresolved bare reference keeps name as URL and in ref', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::LinkReference] = true;
	$doc = $texy->parse('viz [wiki#kotva] stranka');
	$link = null;
	(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $n) use (&$link): ?int {
		if ($n instanceof Texy\Nodes\LinkNode) {
			$link = $n;
		}
		return null;
	});

	Assert::same('wiki#kotva', $link->ref);
	Assert::same('wiki#kotva', $link->url);
	Assert::same('wiki#kotva', Texy\Helpers::extractText($link->content));
});


test('LinkNode keeps the written reference name after resolution', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse("\"Click\":[link] a \"jinde\":https://example.org\n\n[link]: https://example.com");
	$links = [];
	(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $n) use (&$links): ?int {
		if ($n instanceof Texy\Nodes\LinkNode) {
			$links[] = $n;
		}
		return null;
	});

	Assert::same('link', $links[0]->ref);
	Assert::same('https://example.com', $links[0]->url);
	Assert::null($links[1]->ref); // literal URL has no reference
});


test('bare reference does not swallow neighboring syntaxes', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::LinkReference] = true;

	// image syntax
	Assert::contains('<img src="images/photo.png"', $texy->process('[* photo.png *]'));
	// modifier class
	Assert::same("<p class=\"note\">text</p>\n", $texy->process('text .[note]'));
	// PHP attribute
	Assert::same("<p>atribut #[Requires] zustava</p>\n", $texy->process('atribut #[Requires] zustava'));
	// labeled form is handled by its own syntax, not swallowed by bare reference
	Assert::same("<p><a href=\"odkaz\">text</a></p>\n", $texy->process('[text |odkaz]'));
});
