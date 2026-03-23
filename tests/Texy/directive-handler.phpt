<?php declare(strict_types=1);

/**
 * Test: DirectiveModule - handlers for {{name: ...}} directives.
 */

use Tester\Assert;
use Texy\{Nodes, Texy};

require __DIR__ . '/../bootstrap.php';


test('a handled directive is removed and its value passed on', function () {
	$texy = new Texy;
	$seen = [];
	$texy->config->addDirective('leftbar', function (Nodes\DirectiveNode $directive) use (&$seen) {
		$seen[] = [$directive->value, $directive->args];
	});

	Assert::same("<p>Text</p>\n", $texy->toHtml("{{leftbar: menu, deep}}\nText"));
	Assert::same([['menu, deep', ['menu', 'deep']]], $seen);
});


test('a handler may return a replacement node', function () {
	$texy = new Texy;
	$texy->config->addDirective('include', fn(Nodes\DirectiveNode $d) => new Nodes\HtmlCommentNode("include $d->value"));

	Assert::same("<p>Text<!--include menu--> more</p>\n", $texy->toHtml('Text {{include: menu}} more'));
});


test('an unhandled directive is left alone', function () {
	$texy = new Texy;

	Assert::same("<p>Text {{unknown: x}} more</p>\n", $texy->toHtml('Text {{unknown: x}} more'));
});


test('the name is matched case-insensitively', function () {
	$texy = new Texy;
	$texy->config->addDirective('LeftBar', fn() => null);

	Assert::same("<p>Text</p>\n", $texy->toHtml("{{leftbar: x}}\nText"));
});


test('handlers run before the built-in passes', function () {
	// the point of the hook: a directive may still change configuration that a later
	// pass depends on - here HeadingPass, which balances levels against $top
	$texy = new Texy;
	$texy->config->addDirective('title', function () use ($texy) {
		$texy->config->headingTop = 2;
		return null;
	});

	Assert::contains('<h2>', $texy->toHtml("{{title: Page}}\n\nHeading\n======="));
});


test('{{texy: ...}} keeps working', function () {
	$texy = new Texy;
	$document = $texy->parse('{{texy: nofollow}}');
	$texy->transform($document);

	Assert::true($document->meta['nofollow']);
	Assert::same('', trim($texy->renderHtml($document)));
});


test('fallback handler serves directives with no named handler', function () {
	$texy = new Texy;
	$seen = [];
	$texy->config->addDirective('known', fn() => null);
	$texy->config->setDirectiveFallback(function (Nodes\DirectiveNode $directive) use (&$seen) {
		$seen[] = $directive->name . '=' . $directive->value;
	});

	$texy->toHtml("{{known: a}}\n\n{{unknown: b}}\n\n{{other}}");

	Assert::same(['unknown=b', 'other='], $seen);
});


test('without a fallback an unhandled directive stays in the document', function () {
	$texy = new Texy;

	Assert::contains('{{unknown: b}}', $texy->toHtml('{{unknown: b}}'));
});


test('fallback may replace the node like a named handler', function () {
	$texy = new Texy;
	$texy->config->setDirectiveFallback(fn(Nodes\DirectiveNode $d) => new Nodes\TextNode("[$d->value]"));

	Assert::contains('[b]', $texy->toHtml('{{unknown: b}}'));
});


test('a fallback that returns the node keeps the directive', function () {
	$texy = new Texy;
	$texy->config->setDirectiveFallback(fn(Nodes\DirectiveNode $d) => $d);

	Assert::contains('{{unknown: b}}', $texy->toHtml('{{unknown: b}}'));
});
