<?php declare(strict_types=1);

/**
 * Test: NodeTraverser - node replacement, removal and traversal control.
 */

use Tester\Assert;
use Texy\{Node, NodeTraverser, TraverseAction};
use Texy\Nodes\{ContentNode, ParagraphNode, TextNode};

require __DIR__ . '/../bootstrap.php';


test('TraverseAction::Remove in enter removes node from container', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);

	(new NodeTraverser)->traverse(
		$content,
		enter: fn(Node $node) => $node instanceof TextNode && $node->text === 'two'
			? TraverseAction::Remove
			: null,
	);

	Assert::count(2, $content->children);
	Assert::same(['one', 'three'], array_map(fn($n) => $n->text, $content->children));
});


test('TraverseAction::Remove in leave removes node from container', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two')]);

	(new NodeTraverser)->traverse(
		$content,
		leave: fn(Node $node) => $node instanceof TextNode && $node->text === 'one'
			? TraverseAction::Remove
			: null,
	);

	Assert::count(1, $content->children);
	Assert::same('two', $content->children[0]->text);
});


test('node replacement in container', function () {
	$content = new ContentNode([new TextNode('old')]);

	(new NodeTraverser)->traverse(
		$content,
		enter: fn(Node $node) => $node instanceof TextNode && $node->text === 'old'
			? new TextNode('new')
			: null,
	);

	Assert::same('new', $content->children[0]->text);
});


test('TraverseAction::Stop right after TraverseAction::Remove leaves no null in children', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);

	(new NodeTraverser)->traverse(
		$content,
		enter: function (Node $node) {
			if ($node instanceof TextNode && $node->text === 'one') {
				return TraverseAction::Remove;
			}
			if ($node instanceof TextNode && $node->text === 'two') {
				return TraverseAction::Stop;
			}
			return null;
		},
	);

	Assert::same(['two', 'three'], array_map(fn($n) => $n->text, $content->children));
	foreach ($content->children as $child) {
		Assert::type(Node::class, $child);
	}
});


test('TraverseAction::Stop stops visiting further nodes', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);
	$visited = [];

	(new NodeTraverser)->traverse(
		$content,
		enter: function (Node $node) use (&$visited) {
			if ($node instanceof TextNode) {
				$visited[] = $node->text;
				if ($node->text === 'two') {
					return TraverseAction::Stop;
				}
			}
			return null;
		},
	);

	Assert::same(['one', 'two'], $visited);
});


test('TraverseAction::SkipChildren skips subtree', function () {
	$paragraph = new ParagraphNode(new ContentNode([new TextNode('inner')]));
	$content = new ContentNode([$paragraph, new TextNode('outer')]);
	$visited = [];

	(new NodeTraverser)->traverse(
		$content,
		enter: function (Node $node) use (&$visited) {
			if ($node instanceof TextNode) {
				$visited[] = $node->text;
			}
			return $node instanceof ParagraphNode
				? TraverseAction::SkipChildren
				: null;
		},
	);

	Assert::same(['outer'], $visited);
});


test('removing node from fixed single-child slot throws', function () {
	$paragraph = new ParagraphNode(new ContentNode([new TextNode('text')]));

	Assert::exception(
		fn() => (new NodeTraverser)->traverse(
			$paragraph,
			enter: fn(Node $node) => $node instanceof ContentNode
				? TraverseAction::Remove
				: null,
		),
		LogicException::class,
		'Cannot remove child node of %a% during traversal, its slot does not allow that.',
	);
});


test('removing root returns null', function () {
	$content = new ContentNode([new TextNode('x')]);
	$result = (new NodeTraverser)->traverse(
		$content,
		enter: fn(Node $node) => TraverseAction::Remove,
	);
	Assert::null($result);
});


test('replacing an item with a wrong node type is rejected', function () {
	$row = new Texy\Nodes\TableRowNode([new Texy\Nodes\TableCellNode(new ContentNode)]);
	$table = new Texy\Nodes\TableNode([$row]);

	Assert::exception(
		fn() => (new NodeTraverser)->traverse(
			$table,
			enter: fn(Node $node) => $node instanceof Texy\Nodes\TableRowNode
				? new ParagraphNode(new ContentNode)
				: null,
		),
		TypeError::class,
	);
});


test('a node yielding a slot and a list walks both', function () {
	$caption = new ContentNode([new TextNode('cap')]);
	$table = new Texy\Nodes\TableNode(
		[new Texy\Nodes\TableRowNode([new Texy\Nodes\TableCellNode(new ContentNode([new TextNode('a')]))])],
		caption: $caption,
	);

	$texts = [];
	(new NodeTraverser)->traverse($table, function (Node $node) use (&$texts) {
		if ($node instanceof TextNode) {
			$texts[] = $node->text;
		}
		return null;
	});

	Assert::same(['cap', 'a'], $texts);
});
