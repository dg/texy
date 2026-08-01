<?php declare(strict_types=1);

/**
 * Test: Texy::render() - rendering a document parsed earlier.
 */

use Tester\Assert;
use Texy\NodeHelpers;
use Texy\Nodes;
use Texy\Syntax;
use Texy\Texy;
// NodeHelpers, Nodes a Syntax musí být importované: "use Texy\Texy" zastíní
// jméno Texy, takže Texy\NodeHelpers by se rozvinulo na Texy\Texy\NodeHelpers.

require __DIR__ . '/../bootstrap.php';


test('process() is parse() followed by render()', function () {
	$source = "Heading\n=======\n\nText with a \"link\":https://example.com.";

	$texy = new Texy;
	$expected = $texy->process($source);

	$texy = new Texy;
	Assert::same($expected, $texy->renderHtml($texy->parse($source)));
});


test('rendering the same document twice gives the same output', function () {
	$texy = new Texy;
	$doc = $texy->parse("Heading\n=======\n\nText.");

	Assert::same($texy->renderHtml($doc), $texy->renderHtml($doc));
});


test('the AST can be read and transformed between the two calls', function () {
	$texy = new Texy;
	$doc = $texy->parse("Chapter\n=======\n\nSee \"docs\":https://example.com.");

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

	Assert::contains('<strong>', $texy->renderHtml($doc));

	$texy->htmlOutput->phraseTags[Syntax::Strong] = 'b';
	Assert::contains('<b>', $texy->renderHtml($doc));
});


test('htmlPolicy changed between renders is honoured', function () {
	$texy = new Texy;
	$doc = $texy->parse('text .[secret]');

	Assert::contains('class="secret"', $texy->renderHtml($doc));

	$texy->htmlPolicy->allowedClasses = ['public'];
	Assert::notContains('class="secret"', $texy->renderHtml($doc));
});


test('to*() one-shots are render*() over parse()', function () {
	$source = "Heading\n=======\n\nText with **bold** and a \"link\":https://example.com.";
	$texy = new Texy;

	Assert::same($texy->process($source), $texy->renderHtml($texy->parse($source)));
	Assert::same($texy->toText($source), $texy->renderText($texy->parse($source)));
});
