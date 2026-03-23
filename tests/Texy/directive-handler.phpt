<?php declare(strict_types=1);

/**
 * Test: DirectiveModule - the handler of {{name: ...}} directives.
 */

use Tester\Assert;
use Texy\{Nodes, Texy};

require __DIR__ . '/../bootstrap.php';


test('a handled directive is removed and its value passed on', function () {
	$texy = new Texy;
	$seen = [];
	$texy->config->directiveHandler = function (Nodes\DirectiveNode $directive) use (&$seen) {
		$seen[] = [$directive->value, $directive->args];
	};

	Assert::same("<p>Text</p>\n", $texy->toHtml("{{leftbar: menu, deep}}\nText"));
	Assert::same([['menu, deep', ['menu', 'deep']]], $seen);
});


test('a handler may return a replacement node', function () {
	$texy = new Texy;
	$texy->config->directiveHandler = fn(Nodes\DirectiveNode $d) => match (strtolower($d->name)) {
		'include' => new Nodes\HtmlCommentNode("include $d->value"),
		default => $d,
	};

	Assert::same("<p>Text<!--include menu--> more</p>\n", $texy->toHtml('Text {{include: menu}} more'));
});


test('without a handler a directive stays in the document', function () {
	$texy = new Texy;

	Assert::same("<p>Text {{unknown: x}} more</p>\n", $texy->toHtml('Text {{unknown: x}} more'));
});


test('a handler that returns the node keeps the directive', function () {
	$texy = new Texy;
	$texy->config->directiveHandler = fn(Nodes\DirectiveNode $d) => $d;

	Assert::contains('{{unknown: b}}', $texy->toHtml('{{unknown: b}}'));
});


test('a handler that returns nothing removes the directive', function () {
	$texy = new Texy;
	$texy->config->directiveHandler = fn() => null;

	Assert::same("<p>Text</p>\n", $texy->toHtml("{{unknown: x}}\nText"));
});


test('the handler runs before the built-in passes', function () {
	// the point of the hook: a directive may still change configuration that a later
	// pass depends on - here HeadingPass, which shifts the levels by headingTop
	$texy = new Texy;
	$texy->config->directiveHandler = function () use ($texy) {
		$texy->config->headingTop = 2;
		return null;
	};

	Assert::contains('<h2>', $texy->toHtml("{{title: Page}}\n\nHeading\n======="));
});


test('{{texy: ...}} keeps working and does not reach the handler', function () {
	$texy = new Texy;
	$seen = 0;
	$texy->config->directiveHandler = function () use (&$seen) {
		$seen++;
	};
	$document = $texy->parse('{{texy: nofollow}}');
	$texy->transform($document);

	Assert::true($document->meta['nofollow']);
	Assert::same('', trim($texy->renderHtml($document)));
	Assert::same(0, $seen);
});
