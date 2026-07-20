<?php declare(strict_types=1);

/**
 * Test: Renderer custom handlers
 */

use Tester\Assert;
use Texy\Nodes;
use Texy\Output\Html\Renderer;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('registerHandler replaces default handler', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	// Register custom handler for paragraphs
	$texy->htmlOutput->registerHandler(
		fn(Nodes\ParagraphNode $node, Renderer $g) => $g->protect('<div>custom</div>', Renderer::ContentBlock),
	);

	Assert::match('<div>custom</div>', trim((new Renderer($texy->htmlOutput, $texy))->render($ast)));
});


test('handler receives node data', function () {
	$texy = new Texy;
	$texy->htmlOutput->imageRoot = '/img/';
	$ast = $texy->parse('[* image.jpg *]');

	// Custom handler that accesses node properties
	$texy->htmlOutput->registerHandler(
		fn(Nodes\FigureNode $node, Renderer $g) => $g->protect(
			'<figure><img src="/img/' . htmlspecialchars($node->image->url ?? '') . '" alt="custom"></figure>',
			Renderer::ContentReplaced,
		),
	);

	Assert::contains('<figure><img src="/img/image.jpg" alt="custom"></figure>', (new Renderer($texy->htmlOutput, $texy))->render($ast));
});


test('handler can access Texy configuration', function () {
	$texy = new Texy;
	$texy->headingModule->top = 3;
	$ast = $texy->parse("Title\n=====");

	// Custom handler that modifies result of default handler
	$texy->htmlOutput->registerHandler(
		function (Nodes\HeadingNode $node, Renderer $g, ?Closure $previous) {
			$element = $previous($node, $g);
			$element->attrs['class'] = 'custom';
			return $element;
		},
	);

	// The node.level is already adjusted by headingModule.top
	Assert::contains('class="custom">Title</h', (new Renderer($texy->htmlOutput, $texy))->render($ast));
});
