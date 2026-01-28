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
		fn(Nodes\ParagraphNode $node, Generator $g) => $texy->protect('<div>custom</div>', Texy::CONTENT_BLOCK),
	);

	Assert::match('<div>custom</div>', trim($texy->htmlGenerator->render($ast)));
});


test('handler receives node data', function () {
	$texy = new Texy;
	$texy->imageModule->root = '/img/';
	$ast = $texy->parse('[* image.jpg *]');

	// Custom handler that accesses node properties
	$texy->htmlGenerator->registerHandler(
		fn(Nodes\FigureNode $node, Generator $g) => $texy->protect(
			'<figure><img src="/img/' . htmlspecialchars($node->image->url ?? '') . '" alt="custom"></figure>',
			Texy::CONTENT_REPLACED,
		),
	);

	Assert::contains('<figure><img src="/img/image.jpg" alt="custom"></figure>', $texy->htmlGenerator->render($ast));
});


test('handler can access Texy configuration', function () {
	$texy = new Texy;
	$texy->headingModule->top = 3;
	$ast = $texy->parse("Title\n=====");
	$texy->headingModule->afterParse($ast);

	// Custom handler that uses Texy config
	$texy->htmlGenerator->registerHandler(
		function (Nodes\HeadingNode $node, Generator $g) use ($texy) {
			$content = $g->serialize($g->renderNodes($node->content->children));
			return $texy->protect("<h{$node->level} class=\"custom\">{$content}</h{$node->level}>", Texy::CONTENT_BLOCK);
		},
	);

	// The node.level is already adjusted by headingModule.top
	Assert::contains('class="custom">Title</h', $texy->htmlGenerator->render($ast));
});
