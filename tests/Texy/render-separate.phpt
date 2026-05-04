<?php declare(strict_types=1);

/**
 * Test: Texy::render() - rendering a document parsed earlier.
 */

use Tester\Assert;
use Texy\{NodeHelpers, Nodes, Syntax, Texy};

// NodeHelpers, Nodes a Syntax musí být importované: "use Texy\Texy" zastíní
// jméno Texy, takže Texy\NodeHelpers by se rozvinulo na Texy\Texy\NodeHelpers.

require __DIR__ . '/../bootstrap.php';


test('process() is parse() followed by transform() and render()', function () {
	$source = "Heading\n=======\n\nText with a \"link\":https://example.com.";

	$texy = new Texy;
	$expected = $texy->process($source);

	$texy = new Texy;
	$doc = $texy->parse($source);
	$texy->transform($doc);
	Assert::same($expected, $texy->renderHtml($doc));
});


test('transform() is applied once', function () {
	$texy = new Texy;
	$count = 0;
	$texy->addPass(function () use (&$count) { $count++; });
	$doc = $texy->parse('text');
	Assert::same(0, $count);

	$texy->transform($doc);
	$texy->transform($doc);
	Assert::same(1, $count);
});


test('rendering the same document twice gives the same output', function () {
	$texy = new Texy;
	$doc = $texy->parse("Heading\n=======\n\nText.");
	$texy->transform($doc);

	Assert::same($texy->renderHtml($doc), $texy->renderHtml($doc));
});


test('the AST can be read and transformed between the two calls', function () {
	$texy = new Texy;
	$doc = $texy->parse("Chapter\n=======\n\nSee \"docs\":https://example.com.");
	$texy->transform($doc);

	// read: headings are available without rendering
	Assert::same(['Chapter'], array_map(
		fn(Nodes\HeadingNode $n) => $n->getTitle(),
		Nodes\HeadingNode::collectFrom($doc),
	));

	// transform: rewrite link targets
	foreach (NodeHelpers::find($doc, Nodes\LinkNode::class) as $node) {
		if ($node->url !== null) {
			$node->url .= '?ref=texy';
		}
	}

	Assert::contains('https://example.com?ref=texy', $texy->renderHtml($doc));
});


test('output configuration changed after parsing is honoured', function () {
	$texy = new Texy;
	$doc = $texy->parse('**bold**');
	$texy->transform($doc);

	Assert::contains('<strong>', $texy->renderHtml($doc));

	$texy->htmlOutput->phraseTags[Syntax::Strong] = 'b';
	Assert::contains('<b>', $texy->renderHtml($doc));
});


test('htmlPolicy changed between renders is honoured', function () {
	$texy = new Texy;
	$doc = $texy->parse('text .[secret]');
	$texy->transform($doc);

	Assert::contains('class="secret"', $texy->renderHtml($doc));

	$texy->htmlPolicy->allowedClasses = ['public'];
	Assert::notContains('class="secret"', $texy->renderHtml($doc));
});


test('to*() one-shots are render*() over the transformed document', function () {
	$source = "Heading\n=======\n\nText with **bold** and a \"link\":https://example.com.";
	$texy = new Texy;
	$doc = $texy->parse($source);
	$texy->transform($doc);

	Assert::same($texy->process($source), $texy->renderHtml($doc));
	Assert::same($texy->toText($source), $texy->renderText($doc));
});
