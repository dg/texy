<?php declare(strict_types=1);

/**
 * Test: NodeHelpers - finding nodes and cloning a tree.
 */

use Tester\Assert;
use Texy\{Node, NodeHelpers};
use Texy\Nodes\{ContentNode, ParagraphNode, TextNode};

require __DIR__ . '/../bootstrap.php';


function tree(): ParagraphNode
{
	return new ParagraphNode(new ContentNode([
		new TextNode('one'),
		new ParagraphNode(new ContentNode([new TextNode('two')])),
		new TextNode('three'),
	]));
}


test('find() returns matching nodes in traversal order', function () {
	$found = NodeHelpers::find(tree(), fn(Node $node) => $node instanceof TextNode);

	Assert::count(3, $found);
	Assert::same(['one', 'two', 'three'], array_map(fn(TextNode $n) => $n->text, $found));
});


test('find() returns an empty list when nothing matches', function () {
	Assert::same([], NodeHelpers::find(tree(), fn() => false));
});


test('find() can match the root itself', function () {
	$root = tree();
	$found = NodeHelpers::find($root, fn(Node $node) => $node instanceof ParagraphNode);

	Assert::count(2, $found);
	Assert::same($root, $found[0]);
});


test('findFirst() stops at the first match', function () {
	$node = NodeHelpers::findFirst(tree(), fn(Node $node) => $node instanceof TextNode);

	Assert::type(TextNode::class, $node);
	Assert::same('one', $node->text);
});


test('findFirst() returns null when nothing matches', function () {
	Assert::null(NodeHelpers::findFirst(tree(), fn() => false));
});


test('clone() copies the whole tree', function () {
	$original = tree();
	$copy = NodeHelpers::clone($original);

	Assert::notSame($original, $copy);
	Assert::equal($original, $copy);

	// every node is a distinct instance
	$originalNodes = NodeHelpers::find($original, fn() => true);
	$copyNodes = NodeHelpers::find($copy, fn() => true);
	Assert::count(count($originalNodes), $copyNodes);
	foreach ($originalNodes as $i => $node) {
		Assert::notSame($node, $copyNodes[$i]);
	}
});


test('clone() leaves the original untouched', function () {
	$original = tree();
	$copy = NodeHelpers::clone($original);

	NodeHelpers::findFirst($copy, fn(Node $node) => $node instanceof TextNode)->text = 'changed';

	Assert::same('one', NodeHelpers::findFirst($original, fn(Node $node) => $node instanceof TextNode)->text);
});


test('find() accepts a node class as the filter', function () {
	$found = NodeHelpers::find(tree(), TextNode::class);

	Assert::count(3, $found);
	Assert::same(['one', 'two', 'three'], array_map(fn(TextNode $n) => $n->text, $found));
});


test('findFirst() accepts a node class as the filter', function () {
	Assert::same('one', NodeHelpers::findFirst(tree(), TextNode::class)->text);
});


test('a class filter must be a Node subclass', function () {
	Assert::exception(
		fn() => NodeHelpers::find(tree(), stdClass::class),
		LogicException::class,
		'Filter must be a Texy\Node subclass, stdClass given.',
	);
});
