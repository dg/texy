<?php declare(strict_types=1);

/**
 * Test: parse - Directives (script macros {{...}}).
 */

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
	Assert::same('myCommand', $node->name);
	Assert::null($node->value);
	Assert::same([], $node->args);
});


test('directive with parentheses args', function () {
	$node = parseDirective('{{func(arg1, arg2)}}');
	Assert::type(DirectiveNode::class, $node);
	Assert::same('func', $node->name);
	Assert::same('arg1, arg2', $node->value);
	Assert::same(['arg1', 'arg2'], $node->args);
});


test('directive with colon args', function () {
	$node = parseDirective('{{func: value1, value2}}');
	Assert::type(DirectiveNode::class, $node);
	Assert::same('func', $node->name);
	Assert::same('value1, value2', $node->value);
	Assert::same(['value1', 'value2'], $node->args);
});


test('directive with single arg', function () {
	$node = parseDirective('{{include(file.html)}}');
	Assert::type(DirectiveNode::class, $node);
	Assert::same('include', $node->name);
	Assert::same('file.html', $node->value);
	Assert::same(['file.html'], $node->args);
});


test('directive with hyphen in name', function () {
	$node = parseDirective('{{my-command}}');
	Assert::type(DirectiveNode::class, $node);
	Assert::same('my-command', $node->name);
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
	// the well-forming engine shrinks multiple spaces to single space
	Assert::match(
		<<<'HTML'
			<p>text more</p>
			HTML,
		$html,
	);
});
