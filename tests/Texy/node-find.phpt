<?php declare(strict_types=1);

/**
 * Test: Node::find(), findFirst() and the deep clone.
 */

use Tester\Assert;
use Texy\Node;
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


test('find() returns the nodes of the class in traversal order', function () {
	$found = tree()->find(TextNode::class);

	Assert::count(3, $found);
	Assert::same(['one', 'two', 'three'], array_map(fn(TextNode $n) => $n->text, $found));
});


test('find() keeps the nodes the filter accepts', function () {
	$found = tree()->find(TextNode::class, fn(TextNode $n) => $n->text !== 'two');

	Assert::same(['one', 'three'], array_map(fn(TextNode $n) => $n->text, $found));
});


test('find() returns an empty list when nothing matches', function () {
	Assert::same([], tree()->find(TextNode::class, fn() => false));
});


test('find() can match the root itself', function () {
	$root = tree();
	$found = $root->find(ParagraphNode::class);

	Assert::count(2, $found);
	Assert::same($root, $found[0]);
});


test('findFirst() stops at the first match', function () {
	Assert::same('one', tree()->findFirst(TextNode::class)->text);
	Assert::same('two', tree()->findFirst(TextNode::class, fn(TextNode $n) => $n->text !== 'one')->text);
});


test('findFirst() returns null when nothing matches', function () {
	Assert::null(tree()->findFirst(TextNode::class, fn() => false));
});


test('clone copies the whole tree', function () {
	$original = tree();
	$copy = clone $original;

	Assert::notSame($original, $copy);
	Assert::equal($original, $copy);

	// every node is a distinct instance
	$originalNodes = $original->find(Node::class);
	$copyNodes = $copy->find(Node::class);
	Assert::count(count($originalNodes), $copyNodes);
	foreach ($originalNodes as $i => $node) {
		Assert::notSame($node, $copyNodes[$i]);
	}
});


test('clone leaves the original untouched', function () {
	$original = tree();
	$copy = clone $original;

	$copy->findFirst(TextNode::class)->text = 'changed';

	Assert::same('one', $original->findFirst(TextNode::class)->text);
});
