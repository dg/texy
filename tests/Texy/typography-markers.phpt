<?php declare(strict_types=1);

/**
 * Test: the bytes TextRunPass marks with cannot be faked by the text of a node.
 */

use Tester\Assert;
use Texy\Nodes\{ContentNode, DocumentNode, ImageNode, ParagraphNode, PhraseNode, TextNode};
use Texy\{Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


function paragraphOf(ContentNode $content): DocumentNode
{
	return new DocumentNode(new ContentNode([new ParagraphNode($content)]));
}


foreach (["\x15", "\x16", "\x17"] as $byte) {
	test('a marker byte in the text stays where it is and moves nothing', function () use ($byte) {
		$first = new TextNode("A{$byte}B ");
		$inner = new TextNode('C');
		$last = new TextNode(' D...');
		$document = paragraphOf(new ContentNode([$first, new PhraseNode(new ContentNode([$inner]), Syntax::Strong), $last]));

		(new Texy)->transform($document);

		Assert::same("A{$byte}B ", $first->text);
		Assert::same('C', $inner->text);
		Assert::same(" D\u{2026}", $last->text);
	});
}


test('all marker bytes at once survive a change of the text', function () {
	$node = new TextNode("\x15a\x16b\x17 ...");
	(new Texy)->transform(paragraphOf(new ContentNode([$node])));

	Assert::same("\x15a\x16b\x17 \u{2026}", $node->text);
});


test('the stand-in characters occurring in the text are not mistaken for marker bytes', function () {
	$node = new TextNode("\u{E000}\x15\u{E001}\x16\u{E003} ...");
	(new Texy)->transform(paragraphOf(new ContentNode([$node])));

	Assert::same("\u{E000}\x15\u{E001}\x16\u{E003} \u{2026}", $node->text);
});


test('a marker byte in an alternative text is kept', function () {
	$image = new ImageNode('a.jpg', "x\x17 y...");
	(new Texy)->transform(paragraphOf(new ContentNode([$image])));

	Assert::same("x\x17 y\u{2026}", $image->alt);
});
