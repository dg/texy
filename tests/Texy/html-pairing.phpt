<?php declare(strict_types=1);

/**
 * Test: HtmlPairingPass - pairing passthrough tags into HtmlElementNode.
 */

use Tester\Assert;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


function firstParagraph(string $text): Nodes\ParagraphNode
{
	$texy = new Texy\Texy;
	$doc = $texy->parse($text);
	$node = $doc->content->children[0];
	Assert::type(Nodes\ParagraphNode::class, $node);
	return $node;
}


test('matching tags become HtmlElementNode with children', function () {
	$children = firstParagraph('a <b>bold <i>both</i></b> c')->content->children;

	Assert::type(Nodes\TextNode::class, $children[0]);
	Assert::type(Nodes\HtmlElementNode::class, $children[1]);
	$b = $children[1];
	Assert::same('b', $b->name);
	Assert::type(Nodes\TextNode::class, $b->content->children[0]);
	Assert::type(Nodes\HtmlElementNode::class, $b->content->children[1]);
	Assert::same('i', $b->content->children[1]->name);
});


test('crossed tags: outer pairs, inner stays standalone', function () {
	$children = firstParagraph('<b><i>text</b> rest')->content->children;

	Assert::type(Nodes\HtmlElementNode::class, $children[0]);
	Assert::same('b', $children[0]->name);
	// the <i> could not be paired and stays a standalone tag inside <b>
	Assert::type(Nodes\HtmlTagNode::class, $children[0]->content->children[0]);
	Assert::same('i', $children[0]->content->children[0]->name);
});


test('stray closing tag stays standalone', function () {
	$children = firstParagraph('text </b> more')->content->children;

	Assert::type(Nodes\HtmlTagNode::class, $children[1]);
	Assert::true($children[1]->closing);
});


test('unmatched opening tag stays standalone', function () {
	$children = firstParagraph('<b>never closed')->content->children;

	Assert::type(Nodes\HtmlTagNode::class, $children[0]);
	Assert::false($children[0]->closing);
	Assert::type(Nodes\TextNode::class, $children[1]);
});


test('void elements are leaves', function () {
	$children = firstParagraph('a <br> b')->content->children;

	Assert::type(Nodes\HtmlTagNode::class, $children[1]);
	Assert::same('br', $children[1]->name);
});


test('closing tag with different case is preserved for faithful output', function () {
	$para = firstParagraph('<B>text</b>');
	$el = $para->content->children[0];
	Assert::type(Nodes\HtmlElementNode::class, $el);
	Assert::same('B', $el->name);
	Assert::same('b', $el->closingTag->name);
});


test('pairing is render-neutral', function () {
	foreach ([
		'a <b>bold <i>both</i></b> c',
		'<b><i>crossed</b></i> rest',
		'text </b> stray',
		'<span title="x">obsah</span> a <br> konec',
		"<div>blok</div>\n\ntext",
	] as $input) {
		$texy = new Texy\Texy;
		Assert::same($texy->process($input), (new Texy\Texy)->process($input));
	}
});


test('paired element renders same as tag stream', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>a <b>bold</b> c</p>\n",
		$texy->process('a <b>bold</b> c'),
	);
});
