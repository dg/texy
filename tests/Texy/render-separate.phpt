<?php declare(strict_types=1);

/**
 * Test: Texy::renderHtml() - rendering a document parsed earlier.
 */

use Tester\Assert;
use Texy\{Nodes, Parsing, Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


test('toHtml() is parse() followed by transform() and renderHtml()', function () {
	$source = "Heading\n=======\n\nText with a \"link\":https://example.com.";

	$texy = new Texy;
	$expected = $texy->toHtml($source);

	$texy = new Texy;
	$doc = $texy->parse($source);
	$texy->transform($doc);
	Assert::same($expected, $texy->renderHtml($doc));
});


test('a second transform() of a document throws', function () {
	$texy = new Texy;
	$count = 0;
	$texy->addPass(function () use (&$count) { $count++; });
	$doc = $texy->parse('text');
	Assert::same(0, $count);

	$texy->transform($doc);
	Assert::same(1, $count);
	Assert::exception(fn() => $texy->transform($doc), LogicException::class, 'A document is transformed only once, this one is Done.');
	Assert::exception(fn() => (new Texy)->transform($doc), LogicException::class, 'A document is transformed only once, this one is Done.');
	Assert::same(1, $count);
});


test('a transform() nested in a pass throws', function () {
	$texy = new Texy;
	$texy->addPass(fn($doc) => $texy->transform($doc));
	$doc = $texy->parse('text');

	Assert::exception(fn() => $texy->transform($doc), LogicException::class, 'A document is transformed only once, this one is Running.');
});


test('a document whose transformation failed cannot be transformed again', function () {
	$texy = new Texy;
	$texy->addPass(fn() => throw new RuntimeException('failed'));
	$doc = $texy->parse('text');

	Assert::exception(fn() => $texy->transform($doc), RuntimeException::class, 'failed');
	Assert::exception(
		fn() => (new Texy)->transform($doc),
		LogicException::class,
		'A document is transformed only once, this one is Failed.',
	);
});


test('a clone of a syntax tree is a new document, a clone of a finished one stays finished', function () {
	$texy = new Texy;
	$doc = $texy->parse('text');
	$syntaxTree = clone $doc;

	$texy->transform($doc);
	$texy->transform($syntaxTree);

	Assert::exception(
		fn() => $texy->transform(clone $doc),
		LogicException::class,
		'A document is transformed only once, this one is Done.',
	);
});


test('a document is transformed in the language that parsed it, one of no language in any', function () {
	$parse = fn(?string $language) => new Parsing\Engine($language)->parse('text', [], singleLine: true);

	Assert::exception(
		fn() => (new Texy)->transform($parse('Markdown')),
		LogicException::class,
		'The document was parsed as Markdown, transform it in that language.',
	);
	Assert::noError(fn() => (new Texy)->transform($parse('Texy')));
	Assert::noError(fn() => (new Texy)->transform($parse(null)));
	Assert::noError(fn() => (new Texy)->transform(new Nodes\DocumentNode));
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
		$doc->getHeadings(),
	));

	// transform: rewrite link targets
	foreach ($doc->find(Nodes\LinkNode::class) as $node) {
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

	Assert::same($texy->toHtml($source), $texy->renderHtml($doc));
	Assert::same($texy->toText($source), $texy->renderText($doc));
});
