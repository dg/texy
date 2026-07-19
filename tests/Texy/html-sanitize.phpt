<?php declare(strict_types=1);

/**
 * Test: HtmlSanitizePass - tag whitelist evaluated in the transform phase.
 */

use Tester\Assert;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


test('rejected tags become text nodes in the AST', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => Texy\Texy::ALL];
	$doc = $texy->parse('a <u>x</u> <b>y</b>');
	$children = $doc->content->children[0]->content->children;

	// <u>...</u> is rejected: open text, content, close text
	Assert::type(Nodes\TextNode::class, $children[1]);
	Assert::same('<u>', $children[1]->text);
	Assert::type(Nodes\TextNode::class, $children[2]);
	Assert::same('x', $children[2]->text);
	Assert::type(Nodes\TextNode::class, $children[3]);
	Assert::same('</u>', $children[3]->text);

	// <b>...</b> is allowed and stays a paired element
	Assert::type(Nodes\HtmlElementNode::class, $children[5]);
});


test('nested rejected elements are sanitized recursively', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => Texy\Texy::ALL];
	$doc = $texy->parse('<u><i>x</i></u> a <u><b>y</b></u>');
	$children = $doc->content->children[0]->content->children;

	// everything of <u><i>x</i></u> is text
	foreach ([0, 1, 2, 3, 4] as $i) {
		Assert::type(Nodes\TextNode::class, $children[$i]);
	}
	// allowed <b> inside rejected <u> survives as element
	Assert::type(Nodes\HtmlElementNode::class, $children[7]);
	Assert::same('b', $children[7]->name);
});


test('rejected pair is escaped consistently in output', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same(
		"<p>&lt;u&gt;text&lt;/u&gt; ok</p>\n",
		$texy->process('<u>text</u> ok'),
	);
});


test('escaped tag text participates in typography like the string pipeline', function () {
	$run = function (bool $ast): string {
		$texy = new Texy\Texy;
		Texy\Configurator::safeMode($texy);
		$texy->astTypography = $ast;
		$texy->htmlOutput->lineWrap = 0;
		return $texy->process('text <font size="3">x</font> konec');
	};

	Assert::same($run(false), $run(true));
	Assert::contains('&lt;font size=„3“&gt;', $run(true));
});


test('Markdown output no longer emits rejected tags as active HTML', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$doc = $texy->parse('<u>klik</u> text');
	$md = new Texy\Output\Markdown\Renderer;
	Assert::same("\\<u\\>klik\\</u\\> text\n", $md->render($doc));
});


test('dangerous URL scheme is neutralized in transform phase', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$result = $texy->process('<a href="javascript:evil()">click</a>');
	Assert::notContains('<a href', $result); // no active link
	Assert::contains('&lt;a href', $result); // escaped as text
});
