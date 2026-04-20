<?php declare(strict_types=1);

/**
 * Test: Images with custom handler.
 */

use Tester\Assert;
use Texy\Nodes\{ImageNode, LinkNode};

require __DIR__ . '/../bootstrap.php';


test('custom image handler', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->imageRoot = '../images/';
	$texy->htmlOutput->lineWrap = 180;

	// a transform pass modifies the ImageNodes in the AST
	$texy->addPass(function (Texy\Nodes\DocumentNode $doc) {
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
				$node->isImageLink = true;  // Use imageRoot for link href
			} elseif ($node instanceof ImageNode && $node->url === 'user') { // Handle ImageNode directly (image without link)
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
