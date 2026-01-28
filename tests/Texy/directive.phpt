<?php

/**
 * Test: parse - Directives (script macros {{...}}).
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\DirectiveNode;

require __DIR__ . '/../bootstrap.php';


function parseDirective(string $text): ?DirectiveNode
{
	$texy = new Texy\Texy;
	$ast = $texy->parse($text);
	// Directive is inside paragraph content
	foreach ($ast->content->children as $block) {
		if ($block instanceof Texy\Nodes\ParagraphNode) {
			foreach ($block->content->children as $inline) {
				if ($inline instanceof DirectiveNode) {
					return $inline;
				}
			}
		}
	}
	return null;
}


test('simple directive', function () {
	$node = parseDirective('{{myCommand}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent();
	Assert::same('myCommand', $parsed['name']);
	Assert::null($parsed['value']);
	Assert::same([], $parsed['args']);
});


test('directive with parentheses args', function () {
	$node = parseDirective('{{func(arg1, arg2)}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent();
	Assert::same('func', $parsed['name']);
	Assert::same('arg1, arg2', $parsed['value']);
	Assert::same(['arg1', 'arg2'], $parsed['args']);
});


test('directive with colon args', function () {
	$node = parseDirective('{{func: value1, value2}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent();
	Assert::same('func', $parsed['name']);
	Assert::same('value1, value2', $parsed['value']);
	Assert::same(['value1', 'value2'], $parsed['args']);
});


test('directive with single arg', function () {
	$node = parseDirective('{{include(file.html)}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent();
	Assert::same('include', $parsed['name']);
	Assert::same('file.html', $parsed['value']);
	Assert::same(['file.html'], $parsed['args']);
});


test('directive with hyphen in name', function () {
	$node = parseDirective('{{my-command}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent();
	Assert::same('my-command', $parsed['name']);
});


test('custom separator', function () {
	$node = parseDirective('{{func(a|b|c)}}');
	Assert::type(DirectiveNode::class, $node);
	$parsed = $node->parseContent('|');
	Assert::same('func', $parsed['name']);
	Assert::same('a|b|c', $parsed['value']);
	Assert::same(['a', 'b', 'c'], $parsed['args']);
});


test('unknown directive is preserved in output', function () {
	$texy = new Texy\Texy;
	$html = $texy->process('text {{directive}} more');
	Assert::match(
		<<<'HTML'
			<p>text {{directive}} more</p>
			HTML,
		$html,
	);
});


test('texy directive without args is preserved', function () {
	$texy = new Texy\Texy;
	$html = $texy->process('text {{ texy }} more');
	Assert::match(
		<<<'HTML'
			<p>text {{ texy }} more</p>
			HTML,
		$html,
	);
});


test('texy directive with args returns empty', function () {
	$texy = new Texy\Texy;
	$html = $texy->process('text {{ texy (test) }} more');
	// Formatter shrinks multiple spaces to single space
	Assert::match(
		<<<'HTML'
			<p>text more</p>
			HTML,
		$html,
	);
});
