<?php

/**
 * Test: parse - Link and image definitions.
 *
 * Note: Currently, definitions are processed in beforeParse handlers
 * and removed from text before BlockParser sees them. This means they
 * don't create AST nodes. This test verifies current behavior.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('link definition is processed in preprocessing', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("[google]: https://google.com\n\nSome text");

	// Definition is removed during preprocessing, only paragraph remains
	Assert::count(1, $ast->content);
	Assert::type(Texy\Nodes\ParagraphNode::class, $ast->content[0]);
});


test('image definition is processed in preprocessing', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("[*logo*]: /images/logo.png\n\nText");

	// Definition is removed during preprocessing, only paragraph remains
	Assert::count(1, $ast->content);
	Assert::type(Texy\Nodes\ParagraphNode::class, $ast->content[0]);
});


test('definition does not appear in output', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("[ref]: https://example.com\n\nParagraph text");
	Assert::same("<p>Paragraph text</p>\n", $html);
});
