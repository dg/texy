<?php declare(strict_types=1);

/**
 * Test: ContentNode - getChildren() traversal and null cleanup.
 */

use Tester\Assert;
use Texy\Nodes\ContentNode;
use Texy\Nodes\TextNode;

require __DIR__ . '/../bootstrap.php';


test('setting child to null during traversal removes it', function () {
	$node1 = new TextNode('one');
	$node2 = new TextNode('two');
	$node3 = new TextNode('three');

	$content = new ContentNode([$node1, $node2, $node3]);

	// Traversal - set middle node to null via reference
	foreach ($content->getChildren() as &$node) {
		if ($node->text === 'two') {
			$node = null;
		}
	}
	unset($node); // Required after foreach with reference in PHP

	// After traversal, null should be removed and array reindexed
	Assert::count(2, $content->children);
	Assert::same([0, 1], array_keys($content->children)); // verify it's a list
	Assert::same('one', $content->children[0]->text);
	Assert::same('three', $content->children[1]->text);
});
