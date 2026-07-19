<?php declare(strict_types=1);

/**
 * Test: Range tracking for AST nodes.
 *
 * Tests verify that all AST nodes have correct position (offset, length)
 * by comparing full AST dump output with expected files.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../AstDumper.php';


test('headings position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-heading.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-heading.txt',
		AstDumper::dump($doc),
	);
});


test('inline formatting position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-inline.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-inline.txt',
		AstDumper::dump($doc),
	);
});


test('lists position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-list.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-list.txt',
		AstDumper::dump($doc),
	);
});


test('blocks position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-block.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-block.txt',
		AstDumper::dump($doc),
	);
});


test('table position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-table.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-table.txt',
		AstDumper::dump($doc),
	);
});


test('images and links position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-image-link.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-image-link.txt',
		AstDumper::dump($doc),
	);
});


test('UTF-8 positions are byte-based', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-utf8.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-utf8.txt',
		AstDumper::dump($doc),
	);
});


test('nested structures position', function () {
	$texy = new Texy\Texy;
	$text = $texy->preprocess(file_get_contents(__DIR__ . '/sources/position-nested.texy'));
	$doc = $texy->parse($text);

	Assert::matchFile(
		__DIR__ . '/expected/position-nested.txt',
		AstDumper::dump($doc),
	);
});


function extractBySource(Texy\Nodes\DocumentNode $doc, string $src, string $nodeClass): ?string
{
	$found = null;
	(new Texy\NodeTraverser)->traverse($doc, function (Texy\Node $node) use (&$found, $src, $nodeClass): ?int {
		if ($node instanceof $nodeClass && $node->range !== null) {
			$found = substr($src, $node->range->offset, $node->range->length);
			return Texy\NodeTraverser::StopTraversal;
		}
		return null;
	});
	return $found;
}


test('positions inside list item with blank line between continuation lines', function () {
	$texy = new Texy\Texy;
	$src = $texy->preprocess("- první řádek\n\n  pokračování **tučné**");
	$doc = $texy->parse($src);

	Assert::same('**tučné**', extractBySource($doc, $src, Texy\Nodes\PhraseNode::class));
});


test('positions inside multiline table cell (rowspan)', function () {
	$texy = new Texy\Texy;
	$src = $texy->preprocess("| první **tučný** | x\n| pokračování ^| y");
	$doc = $texy->parse($src);

	Assert::same('**tučný**', extractBySource($doc, $src, Texy\Nodes\PhraseNode::class));
});


test('positions inside div block with indented content', function () {
	$texy = new Texy\Texy;
	$src = $texy->preprocess("/--div\n  odstavec **tučný**\n\\--");
	$doc = $texy->parse($src);

	Assert::same('**tučný**', extractBySource($doc, $src, Texy\Nodes\PhraseNode::class));
});


test('paragraph line merging and modifier keep exact inline ranges', function () {
	$texy = new Texy\Texy;
	$text = "prvni radek\n   pokracovani **tucne** dal\ndalsi text .[trida]";
	$doc = $texy->parse($text);
	$para = $doc->content->children[0];

	Assert::same('trida', $para->modifier->classes ? array_key_first($para->modifier->classes) : null);

	$check = function (Texy\Node $n) use ($text, &$check): void {
		foreach ($n->getChildren() as $child) {
			$check($child);
		}
		if ($n instanceof Texy\Nodes\PhraseNode && $n->range !== null) {
			Assert::same('**tucne**', substr($text, $n->range->offset, $n->range->length));
		}
	};
	$check($para);

	// text node after the hard break must point at its source position
	$found = false;
	$walk = function (Texy\Node $n) use (&$walk, &$found, $text): void {
		foreach ($n->getChildren() as $child) {
			$walk($child);
		}
		if ($n instanceof Texy\Nodes\TextNode && str_starts_with($n->text, 'pokracovani')) {
			$found = true;
			Assert::same('pokracovani ', substr($text, $n->range->offset, 12));
		}
	};
	$walk($para);
	Assert::true($found);
});
