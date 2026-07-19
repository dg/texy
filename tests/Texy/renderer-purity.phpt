<?php declare(strict_types=1);

/**
 * Test: HTML Renderer is a pure function of the AST - rendering does not
 * mutate nodes, repeated renders give identical output and directives
 * do not leak between documents.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('repeated render of the same AST gives identical output', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse(
		<<<'TEXY'
			Hello **world** "link .[nofollow]":https://example.com

			[* image.png .(The title) <]

			[* figure.png .(Caption) >]:https://example.com *** Some caption

			> quoted text
			TEXY,
	);

	$html1 = (new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc);
	$html2 = (new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc);
	Assert::same($html1, $html2);
});


test('rendering does not mutate image modifier', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('text [* image.png .(The title) <] text');

	$paragraph = $doc->content->children[0];
	$image = $paragraph->content->children[1];
	Assert::type(Texy\Nodes\ImageNode::class, $image);
	Assert::same('The title', $image->modifier->title);
	Assert::same('left', $image->modifier->hAlign);

	(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc);

	Assert::same('The title', $image->modifier->title);
	Assert::same('left', $image->modifier->hAlign);
});


test('rendering does not mutate link modifier (nofollow class)', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('"link .[nofollow]":https://example.com');

	$paragraph = $doc->content->children[0];
	$link = $paragraph->content->children[0];
	Assert::type(Texy\Nodes\LinkNode::class, $link);
	Assert::true(isset($link->modifier->classes['nofollow']));

	(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc);

	Assert::true(isset($link->modifier->classes['nofollow']));
});


test('rendering does not mutate image node inside figure', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('[* figure.png >] *** Caption');

	$figure = $doc->content->children[0];
	Assert::type(Texy\Nodes\FigureNode::class, $figure);
	$image = $figure->image;
	Assert::type(Texy\Nodes\ImageNode::class, $image);
	Assert::same('right', $image->modifier->hAlign);

	(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($doc);

	Assert::same('right', $image->modifier->hAlign);
});


test('nofollow directive applies to whole document including preceding links', function () {
	$texy = new Texy\Texy;
	$html = $texy->process(
		<<<'TEXY'
			"before":https://example.com

			{{texy: nofollow}}

			"after":https://example.com
			TEXY,
	);
	Assert::match(
		<<<'HTML'
			<p><a href="https://example.com" rel="nofollow">before</a></p>

			<p><a href="https://example.com" rel="nofollow">after</a></p>
			HTML,
		$html,
	);
});


test('nofollow directive does not leak into next document processed by the same instance', function () {
	$texy = new Texy\Texy;
	$texy->process("\"link\":https://example.com\n\n{{texy: nofollow}}");

	Assert::false($texy->htmlOutput->linkNoFollow);
	Assert::match(
		'<p><a href="https://example.com">link</a></p>',
		$texy->process('"link":https://example.com'),
	);
});


test('directive of one document does not affect rendering of another AST', function () {
	$texy = new Texy\Texy;
	$texy->process("{{texy: nofollow}}\n\n\"a\":https://example.com");

	$docB = $texy->parse('"b":https://example.com');
	Assert::match(
		'<p><a href="https://example.com">b</a></p>',
		(new \Texy\Output\Html\Renderer($texy->htmlOutput, $texy))->render($docB),
	);
});


test('paragraph containing only a consumed directive produces no output', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("first\n\n{{texy: nofollow}}\n\nlast");
	Assert::match(
		<<<'HTML'
			<p>first</p>

			<p>last</p>
			HTML,
		$html,
	);
});
