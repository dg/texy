<?php declare(strict_types=1);

/**
 * Test: NodeRenderer renderer dispatch - inheritance fallback and validation.
 */

use Tester\Assert;
use Texy\{Nodes, Texy};
use Texy\Output\Html;

require __DIR__ . '/../bootstrap.php';


class CustomImageNode extends Nodes\ImageNode
{
}


/** Builds a document holding one node of the given class. */
function documentWith(Texy $texy, Nodes\InlineNode $node): Nodes\DocumentNode
{
	$doc = $texy->parse('');
	$doc->content->children[] = new Nodes\ParagraphNode(new Nodes\ContentNode([$node]));
	return $doc;
}


/** Renders a document to HTML. */
function renderDoc(Texy $texy, Nodes\DocumentNode $doc): string
{
	return (new Html\Renderer($texy->htmlOutput, $texy))->render($doc);
}


test('a subclass without its own handler renders like its parent', function () {
	$texy = new Texy;
	$plain = renderDoc($texy, documentWith($texy, new Nodes\ImageNode('foo.png')));
	$custom = renderDoc($texy, documentWith($texy, new CustomImageNode('foo.png')));

	Assert::same($plain, $custom);
});


test('a renderer for the subclass wins and can delegate to the parent one', function () {
	$texy = new Texy;
	$texy->htmlOutput->registerHandler(
		function (CustomImageNode $node, Html\Renderer $renderer, ?Closure $previous) {
			$el = $previous($node, $renderer);
			$el->attrs['data-custom'] = 'yes';
			return $el;
		},
	);

	Assert::contains('data-custom="yes"', renderDoc($texy, documentWith($texy, new CustomImageNode('foo.png'))));
	// the parent class stays untouched
	Assert::notContains('data-custom', renderDoc($texy, documentWith($texy, new Nodes\ImageNode('foo.png'))));
});


test('a renderer that could never be dispatched to is refused when registered', function () {
	$cases = [
		'nullable' => fn(?Nodes\ImageNode $n, $g, $p) => null,
		'union' => fn(Nodes\ImageNode|Nodes\LinkNode $n, $g, $p) => null,
		'untyped' => fn($n, $g, $p) => null,
		'not a node' => fn(stdClass $n, $g, $p) => null,
	];

	foreach ($cases as $label => $handler) {
		Assert::exception(
			fn() => (new Texy)->htmlOutput->registerHandler($handler),
			LogicException::class,
			"Renderer's first parameter must be typed as a non-nullable Texy\\Node subclass, %a%",
			null,
			"case: $label",
		);
	}

	// a renderer taking no parameter at all says so, instead of blaming a missing type
	Assert::exception(
		fn() => (new Texy)->htmlOutput->registerHandler(fn() => null),
		LogicException::class,
		'%a% no parameter given.',
	);
});


test('a valid renderer is accepted', function () {
	$texy = new Texy;
	Assert::noError(function () use ($texy) {
		$texy->htmlOutput->registerHandler(
			fn(Nodes\ImageNode $n, Html\Renderer $g, ?Closure $p) => null,
		);
	});
});


test('a node no renderer knows degrades to its content', function () {
	$texy = new Texy;
	$node = new class (new Nodes\ContentNode([new Nodes\TextNode('inside')])) extends Nodes\InlineNode {
		public function __construct(
			public Nodes\ContentNode $content,
		) {
		}


		public function &getChildren(): Generator
		{
			yield $this->content;
		}
	};

	Assert::same("<p>inside</p>\n", renderDoc($texy, documentWith($texy, $node)));
});


test('a childless unknown node renders to nothing rather than failing', function () {
	$texy = new Texy;
	$node = new class extends Nodes\InlineNode {
	};

	Assert::noError(fn() => renderDoc($texy, documentWith($texy, $node)));
});
