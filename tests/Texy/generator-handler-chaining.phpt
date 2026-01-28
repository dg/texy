<?php

/**
 * Test: Generator handler chaining with $previous parameter
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes;
use Texy\Output\Html;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('handler receives $previous parameter', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	$previousCalled = false;

	$texy->htmlGenerator->registerHandler(
		function (Nodes\ParagraphNode $node, Html\Generator $gen, ?Closure $previous) use (&$previousCalled): Html\Element|string {
			$previousCalled = $previous !== null;
			// Call previous handler
			$res = $previous($node, $gen);
			Assert::type(Html\Element::class, $res);
			return $res;
		},
	);

	// Default handler should render <p>hello</p>
	Assert::match('<p>hello</p>', trim($texy->htmlGenerator->render($ast)));
	Assert::true($previousCalled);
});


test('handler can delegate to previous handler by returning null', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	$texy->htmlGenerator->registerHandler(
		fn(Nodes\ParagraphNode $node, Html\Generator $gen, ?Closure $previous): ?Html\Element => null,
	);

	Assert::match('<p>hello</p>', trim($texy->htmlGenerator->render($ast)));
});


test('handler chain with conditional delegation', function () {
	$texy = new Texy;
	$ast = $texy->parse("hello\n\nworld");

	$customCount = 0;

	$texy->htmlGenerator->registerHandler(
		function (Nodes\ParagraphNode $node, Html\Generator $gen, ?Closure $previous) use (&$customCount): Html\Element|string|null {
			// Only handle paragraphs containing "hello"
			$content = $gen->serialize($gen->renderNodes($node->content->children));
			if (str_contains($content, 'hello')) {
				$customCount++;
				$el = new Html\Element('div');
				$el->attrs['class'] = 'custom';
				$el->children = [$content];
				return $el;
			}
			// Delegate other paragraphs to previous handler
			return null;
		},
	);

	$html = $texy->htmlGenerator->render($ast);
	Assert::same(1, $customCount);
	Assert::match('<div class="custom">hello</div>%A%<p>world</p>', trim($html));
});


test('multiple handlers form a chain', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	$calls = [];

	// First handler (registered first, called last in chain)
	$texy->htmlGenerator->registerHandler(
		function (Nodes\ParagraphNode $node, Html\Generator $gen, ?Closure $previous) use (&$calls): Html\Element|string {
			$calls[] = 'first';
			$el = new Html\Element('p');
			$el->attrs['data-first'] = '1';
			$el->children = $gen->renderNodes($node->content->children);
			return $el;
		},
	);

	// Second handler (registered second, called first, can delegate to first)
	$texy->htmlGenerator->registerHandler(
		function (Nodes\ParagraphNode $node, Html\Generator $gen, ?Closure $previous) use (&$calls): Html\Element|string {
			$calls[] = 'second';
			// Delegate to first handler
			$result = $previous($node, $gen);
			// Modify result
			if ($result instanceof Html\Element) {
				$result->attrs['data-second'] = '1';
			}
			return $result;
		},
	);

	$html = $texy->htmlGenerator->render($ast);

	// Second handler called first, then delegates to first
	Assert::same(['second', 'first'], $calls);
	Assert::match('<p data-first="1" data-second="1">hello</p>', trim($html));
});
