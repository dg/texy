<?php declare(strict_types=1);

/**
 * Test: DirectiveModule - handlers for {{name: ...}} directives.
 */

use Tester\Assert;
use Texy\Nodes;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('a handled directive is removed and its value passed on', function () {
	$texy = new Texy;
	$seen = [];
	$texy->directiveModule->addDirective('leftbar', function (?string $value, array $args) use (&$seen) {
		$seen[] = [$value, $args];
		return null;
	});

	Assert::same("<p>Text</p>\n", $texy->process("{{leftbar: menu, deep}}\nText"));
	Assert::same([['menu, deep', ['menu', 'deep']]], $seen);
});


test('a handler may return a replacement node', function () {
	$texy = new Texy;
	$texy->directiveModule->addDirective('include', fn(?string $value) => new Nodes\HtmlCommentNode("include $value"));

	Assert::same("<p>Text<!--include menu--> more</p>\n", $texy->process('Text {{include: menu}} more'));
});


test('an unhandled directive is left alone', function () {
	$texy = new Texy;

	Assert::same("<p>Text {{unknown: x}} more</p>\n", $texy->process('Text {{unknown: x}} more'));
});


test('the name is matched case-insensitively', function () {
	$texy = new Texy;
	$texy->directiveModule->addDirective('LeftBar', fn() => null);

	Assert::same("<p>Text</p>\n", $texy->process("{{leftbar: x}}\nText"));
});


test('handlers run before the built-in passes', function () {
	// the point of the hook: a directive may still change configuration that a later
	// pass depends on - here HeadingPass, which balances levels against $top
	$texy = new Texy;
	$texy->directiveModule->addDirective('title', function () use ($texy) {
		$texy->headingModule->top = 2;
		return null;
	});

	Assert::contains('<h2>', $texy->process("{{title: Page}}\n\nHeading\n======="));
});


test('{{texy: ...}} keeps working', function () {
	$texy = new Texy;
	$document = $texy->parse('{{texy: nofollow}}');

	Assert::true($document->meta['nofollow']);
	Assert::same('', trim($texy->renderHtml($document)));
});


test('fallback handler serves directives with no named handler', function () {
	$texy = new Texy;
	$seen = [];
	$texy->directiveModule->addDirective('known', fn() => null);
	$texy->directiveModule->setFallback(function (?string $value, array $args, Nodes\DirectiveNode $node) use (&$seen) {
		$seen[] = $node->parseContent()['name'] . '=' . $value;
		return null;
	});

	$texy->process("{{known: a}}\n\n{{unknown: b}}\n\n{{other}}");

	Assert::same(['unknown=b', 'other='], $seen);
});


test('without a fallback an unhandled directive stays in the document', function () {
	$texy = new Texy;

	Assert::contains('{{unknown: b}}', $texy->process('{{unknown: b}}'));
});


test('fallback may replace the node like a named handler', function () {
	$texy = new Texy;
	$texy->directiveModule->setFallback(fn(?string $value) => new Nodes\TextNode("[$value]"));

	Assert::contains('[b]', $texy->process('{{unknown: b}}'));
});
