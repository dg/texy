<?php

/**
 * Test: parse - Link and image definitions.
 *
 * In the AST-based architecture, definitions create their own nodes
 * (LinkDefinitionNode, ImageDefinitionNode) but these don't output anything.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('link definition creates AST node', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("[google]: https://google.com\n\nSome text");

	// Definition creates a LinkDefinitionNode, then paragraph follows
	Assert::count(2, $ast->content->children);
	Assert::type(Texy\Nodes\LinkDefinitionNode::class, $ast->content->children[0]);
	Assert::type(Texy\Nodes\ParagraphNode::class, $ast->content->children[1]);
});


test('image definition creates AST node', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("[*logo*]: /images/logo.png\n\nText");

	// Definition creates an ImageDefinitionNode, then paragraph follows
	Assert::count(2, $ast->content->children);
	Assert::type(Texy\Nodes\ImageDefinitionNode::class, $ast->content->children[0]);
	Assert::type(Texy\Nodes\ParagraphNode::class, $ast->content->children[1]);
});


test('definition does not appear in output', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("[ref]: https://example.com\n\nParagraph text");
	Assert::same("<p>Paragraph text</p>\n", $html);
});
