<?php declare(strict_types=1);

/**
 * Test: Table caption written as |## Text above the first row.
 */

use Tester\Assert;
use Texy\Helpers;
use Texy\Node;
use Texy\NodeHelpers;
use Texy\Nodes;
use Texy\NodeTraverser;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('caption becomes <caption>, separator space is not content', function () {
	$texy = new Texy;
	Assert::same(
		"<table>\n"
		. "\t<caption>Caption</caption>\n\n"
		. "\t<tbody>\n"
		. "\t\t<tr>\n"
		. "\t\t\t<td>a</td>\n\n"
		. "\t\t\t<td>b</td>\n"
		. "\t\t</tr>\n"
		. "\t</tbody>\n"
		. "</table>\n",
		$texy->process("|## Caption\n| a | b\n"),
	);
});


test('caption content is parsed as inline markup', function () {
	$texy = new Texy;
	Assert::contains(
		'<caption>Caption with <strong>bold</strong></caption>',
		$texy->process("|## Caption with **bold**\n| a | b\n"),
	);
});


test('= is accepted like #, and closing chars are optional', function () {
	$texy = new Texy;
	foreach (["|== Caption\n| a | b\n", "|## Caption ##|\n| a | b\n"] as $source) {
		Assert::contains('<caption>Caption</caption>', $texy->process($source));
	}
});


test('table without caption emits no <caption>', function () {
	$texy = new Texy;
	Assert::notContains('<caption>', $texy->process("| a | b\n"));
});


test('a caption line alone is not a table', function () {
	$texy = new Texy;
	Assert::same("<p>|## Caption</p>\n", $texy->process("|## Caption\n"));
});


test('toText prints the caption above the rows', function () {
	$texy = new Texy;
	Assert::same("Caption\na\tb\n", $texy->toText("|## Caption\n| a | b\n"));
});


test('caption is part of the AST, so a traverser reaches it', function () {
	$texy = new Texy;
	$doc = $texy->parse("|## Caption\n| a | b\n");

	$texts = array_map(
		fn(Nodes\TextNode $node) => $node->text,
		NodeHelpers::find($doc, Nodes\TextNode::class),
	);

	Assert::same(['Caption', 'a', 'b'], $texts);
});


test('rows stay removable and replaceable next to a caption', function () {
	$texy = new Texy;
	$doc = $texy->parse("|## Caption\n| a | b\n| c | d\n");

	(new NodeTraverser)->traverse($doc, fn(Node $node): ?int => $node instanceof Nodes\TableRowNode && Helpers::extractText($node) === 'cd'
			? NodeTraverser::RemoveNode
			: null);

	$table = NodeHelpers::findFirst($doc, Nodes\TableNode::class);

	Assert::count(1, $table->rows);
});
