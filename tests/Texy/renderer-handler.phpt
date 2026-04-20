<?php declare(strict_types=1);

/**
 * Test: Renderer custom handlers
 */

use Tester\Assert;
use Texy\{Nodes, Texy};
use Texy\Output\Html\{Raw, Renderer};

require __DIR__ . '/../bootstrap.php';


test('registerHandler replaces default handler', function () {
	$texy = new Texy;
	$ast = $texy->parse('hello');

	// Register custom handler for paragraphs
	$texy->htmlOutput->addRenderer(
		fn(Nodes\ParagraphNode $node, Renderer $g) => new Raw('<div>custom</div>'),
	);

	Assert::match('<div>custom</div>', trim($texy->createHtmlRenderer()->render($ast)));
});


test('handler receives node data', function () {
	$texy = new Texy;
	$texy->htmlOutput->imageRoot = '/img/';
	$ast = $texy->parse('[* image.jpg *]');

	// Custom handler that accesses node properties
	$texy->htmlOutput->addRenderer(
		fn(Nodes\FigureNode $node, Renderer $g) => new Raw(
			'<figure><img src="/img/' . htmlspecialchars($node->image->url ?? '') . '" alt="custom"></figure>',
		),
	);

	Assert::contains('<figure><img src="/img/image.jpg" alt="custom"></figure>', $texy->createHtmlRenderer()->render($ast));
});


test('handler can access Texy configuration', function () {
	$texy = new Texy;
	$texy->headingModule->top = 3;
	$ast = $texy->parse("Title\n=====");

	// Custom handler that modifies result of default handler
	$texy->htmlOutput->addRenderer(
		function (Nodes\HeadingNode $node, Renderer $g, ?Closure $previous) {
			$element = $previous($node, $g);
			$element->attrs['class'] = 'custom';
			return $element;
		},
	);

	// The node.level is already adjusted by headingModule.top
	Assert::contains('class="custom">Title</h', $texy->createHtmlRenderer()->render($ast));
});
