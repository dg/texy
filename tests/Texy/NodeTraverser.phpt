<?php declare(strict_types=1);

/**
 * Test: NodeTraverser - node replacement, removal and traversal control.
 */

use Tester\Assert;
use Texy\Node;
use Texy\Nodes\ContentNode;
use Texy\Nodes\ParagraphNode;
use Texy\Nodes\TextNode;
use Texy\NodeTraverser;

require __DIR__ . '/../bootstrap.php';


test('RemoveNode in enter removes node from container', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);

	(new NodeTraverser)->traverse(
		$content,
		enter: fn(Node $node) => $node instanceof TextNode && $node->text === 'two'
			? NodeTraverser::RemoveNode
			: null,
	);

	Assert::count(2, $content->children);
	Assert::same(['one', 'three'], array_map(fn($n) => $n->text, $content->children));
});


test('RemoveNode in leave removes node from container', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two')]);

	(new NodeTraverser)->traverse(
		$content,
		leave: fn(Node $node) => $node instanceof TextNode && $node->text === 'one'
			? NodeTraverser::RemoveNode
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


test('StopTraversal right after RemoveNode leaves no null in children', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);

	(new NodeTraverser)->traverse(
		$content,
		enter: function (Node $node) {
			if ($node instanceof TextNode && $node->text === 'one') {
				return NodeTraverser::RemoveNode;
			}
			if ($node instanceof TextNode && $node->text === 'two') {
				return NodeTraverser::StopTraversal;
			}
			return null;
		},
	);

	Assert::same(['two', 'three'], array_map(fn($n) => $n->text, $content->children));
	foreach ($content->children as $child) {
		Assert::type(Node::class, $child);
	}
});


test('StopTraversal stops visiting further nodes', function () {
	$content = new ContentNode([new TextNode('one'), new TextNode('two'), new TextNode('three')]);
	$visited = [];

	(new NodeTraverser)->traverse(
		$content,
		enter: function (Node $node) use (&$visited) {
			if ($node instanceof TextNode) {
				$visited[] = $node->text;
				if ($node->text === 'two') {
					return NodeTraverser::StopTraversal;
				}
			}
			return null;
		},
	);

	Assert::same(['one', 'two'], $visited);
});


test('DontTraverseChildren skips subtree', function () {
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
				? NodeTraverser::DontTraverseChildren
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
				? NodeTraverser::RemoveNode
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
		enter: fn(Node $node) => NodeTraverser::RemoveNode,
	);
	Assert::null($result);
});
