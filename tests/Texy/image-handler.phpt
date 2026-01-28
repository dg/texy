<?php

/**
 * Test: Images with custom handler.
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\ImageNode;
use Texy\Nodes\LinkNode;

require __DIR__ . '/../bootstrap.php';


test('custom image handler', function () {
	$texy = new Texy\Texy;
	$texy->imageModule->root = '../images/';
	$texy->htmlOutputModule->lineWrap = 180;

	// Use afterParse handler to modify ImageNodes in the AST
	$texy->addHandler('afterParse', function (Texy\Nodes\DocumentNode $doc) {
		$traverser = new Texy\NodeTraverser;
		$traverser->traverse($doc, function (Texy\Node $node) {
			// Handle LinkNode wrapping ImageNode (image with link)
			if ($node instanceof LinkNode
				&& ($imageNode = $node->content->children[0] ?? null) instanceof ImageNode
				&& $imageNode->url === 'user'
			) {
				$imageNode->url = 'image.gif';
				$imageNode->modifier ??= new Texy\Modifier;
				$imageNode->modifier->title = 'Texy! logo';
				$node->url = 'image-big.gif';  // Update link URL (root added during generation)
				$node->isImageLink = true;  // Use imageModule->root
			}
			// Handle ImageNode directly (image without link)
			elseif ($node instanceof ImageNode && $node->url === 'user') {
				$node->url = 'image.gif';
				$node->modifier ??= new Texy\Modifier;
				$node->modifier->title = 'Texy! logo';
			}
			return null;
		});
	});

	Assert::matchFile(
		__DIR__ . '/expected/image-handler.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/image-handler.texy')),
	);
});
