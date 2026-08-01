<?php declare(strict_types=1);

/**
 * Test: a node one format does not know degrades to its content in the others.
 */

use Tester\Assert;
use Texy\Nodes;
use Texy\Output;
use Texy\Parsing\ParseContext;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


class AlertNode extends Nodes\BlockNode
{
	public function __construct(
		public Nodes\ContentNode $content,
	) {
	}


	public function &getChildren(): Generator
	{
		yield $this->content;
	}
}


/** Registers a custom block syntax rendered only in HTML. */
function texyWithAlert(): Texy
{
	$texy = new Texy;
	$texy->addBlockSyntax(
		'custom/alert',
		'~^!!!\ (.+)$~',
		fn(ParseContext $context, array $matches, array $offsets) => new AlertNode(
			$context->parseInline($matches[1], $offsets[1]),
		),
	);
	$texy->htmlOutput->addRenderer(
		fn(AlertNode $node, Output\Html\Renderer $gen) => (new Output\Html\Element('div', ['class' => 'alert']))
			->add(...$gen->renderNodes($node->content->children)),
	);
	return $texy;
}


test('the format with a renderer uses it', function () {
	$texy = texyWithAlert();

	Assert::contains('<div class="alert">Watch out</div>', $texy->process('!!! Watch out'));
});


test('the other formats keep the content instead of failing', function () {
	$texy = texyWithAlert();
	$document = $texy->parse("Intro.\n\n!!! Watch out\n\nOutro.");

	Assert::contains('Watch out', (new Output\Text\Renderer)->render($document));
});
