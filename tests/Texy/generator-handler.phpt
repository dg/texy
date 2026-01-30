<?php

/**
 * Test: Generator custom handlers
 */

declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes;
use Texy\Output\Html\Generator;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('registerHandler replaces default handler', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	// Register custom handler for paragraphs
	$texy->htmlGenerator->registerHandler(
		fn(Nodes\ParagraphNode $node, Generator $g) => $g->protect('<div>custom</div>', Generator::ContentBlock),
	);

	Assert::match('<div>custom</div>', trim($texy->htmlGenerator->render($ast)));
});


test('handler receives node data', function () {
	$texy = new Texy;
	$texy->htmlGenerator->imageRoot = '/img/';
	$ast = $texy->parse('[* image.jpg *]');

	// Custom handler that accesses node properties
	$texy->htmlGenerator->registerHandler(
		fn(Nodes\FigureNode $node, Generator $g) => $g->protect(
			'<figure><img src="/img/' . htmlspecialchars($node->image->url ?? '') . '" alt="custom"></figure>',
			Generator::ContentReplaced,
		),
	);

	Assert::contains('<figure><img src="/img/image.jpg" alt="custom"></figure>', $texy->htmlGenerator->render($ast));
});


test('handler can access Texy configuration', function () {
	$texy = new Texy;
	$texy->headingModule->top = 3;
	$ast = $texy->parse("Title\n=====");
	$texy->headingModule->afterParse($ast);

	// Custom handler that modifies result of default handler
	$texy->htmlGenerator->registerHandler(
		function (Nodes\HeadingNode $node, Generator $g, ?Closure $previous) {
			$element = $previous($node, $g);
			$element->attrs['class'] = 'custom';
			return $element;
		},
	);

	// The node.level is already adjusted by headingModule.top
	Assert::contains('class="custom">Title</h', $texy->htmlGenerator->render($ast));
});
