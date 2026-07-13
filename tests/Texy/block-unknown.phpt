<?php declare(strict_types=1);

/**
 * Test: a delimited block of an unknown type is shown as written.
 */

use Tester\Assert;
use Texy\Nodes\{CodeBlockNode, UnknownDelimitedNode};
use Texy\{Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


$block = "/--foo .[x]\n**bold** \"quotes\" :-) <b>x</b>\n  echo 'x';\n\\--";


test('the node holds the whole source of the block', function () use ($block) {
	$src = "intro\n\n$block\n\nafter";
	$node = (new Texy)->parse($src)->findFirst(UnknownDelimitedNode::class);

	Assert::type(UnknownDelimitedNode::class, $node);
	Assert::same('foo', $node->type);
	Assert::same($block, $node->code);
	Assert::null($node->modifier);
	Assert::same($block, substr($src, $node->range->offset, $node->range->length));
	Assert::same(7 + 12, $node->codeMap->toAbsolute(12));
});


test('HTML shows the source escaped, with no inline syntax and no typography', function () use ($block) {
	Assert::same(
		"<pre>/--foo .[x]\n**bold** \"quotes\" :-) &lt;b&gt;x&lt;/b&gt;\n  echo 'x';\n\\--</pre>\n",
		(new Texy)->toHtml($block),
	);
});


test('text output is the source', function () use ($block) {
	Assert::same($block, trim((new Texy)->toText($block)));
});


test('printed back as Texy it is the same source', function () use ($block) {
	$texy = new Texy;
	Assert::same($block . "\n", $texy->renderTexy($texy->parse($block)));
});


test('a block that is not closed reaches the end of the text', function () {
	$src = "/--foo\na\nb";
	$node = (new Texy)->parse($src)->findFirst(UnknownDelimitedNode::class);
	Assert::same($src, $node->code);
	Assert::same(0, $node->range->offset);
	Assert::same(strlen($src), $node->range->length);
});


test('the type is lower-case', function () {
	$node = (new Texy)->parse("/--FOO\nx\n\\--")->findFirst(UnknownDelimitedNode::class);
	Assert::same('foo', $node->type);
	Assert::same("/--FOO\nx\n\\--", $node->code);
});


test('a nested block maps its code to the source', function () {
	foreach (["- item\n\n  /--foo\n  a\n  \\--", "/--div\n  /--foo\n  a\n  \\--\n\\--"] as $src) {
		$texy = new Texy;
		$node = $texy->parse($src)->findFirst(UnknownDelimitedNode::class);
		Assert::same("/--foo\na\n\\--", $node->code, $src);
		Assert::same("/--foo\n  a\n  \\--", substr($src, $node->range->offset, $node->range->length), $src);
		Assert::same('a', $src[$node->codeMap->toAbsolute(7)], $src);
		Assert::contains("<pre>/--foo\na\n\\--</pre>", $texy->toHtml($src), $src);
	}
});


test('a name of a language is no code block', function () {
	$document = (new Texy)->parse("/--php\necho 'x';\n\\--");
	Assert::null($document->findFirst(CodeBlockNode::class));
	Assert::same('php', $document->findFirst(UnknownDelimitedNode::class)->type);
	Assert::contains("echo 'x';", (new Texy)->toHtml("/--php\necho 'x';\n\\--"));
});


test('what is no name at all is an unknown type too', function () {
	Assert::same(
		"<pre>/--.[texy]\nx\n\\--</pre>\n",
		(new Texy)->toHtml("/--.[texy]\nx\n\\--\n"),
	);
});


test('a switched-off built-in type is no unknown type', function () {
	$texy = new Texy;
	$texy->allowedSyntax[Syntax::DelimitedCode] = false;
	$document = $texy->parse("/--code php\nx\n\\--");
	Assert::null($document->findFirst(UnknownDelimitedNode::class));
	Assert::same('<p>/--code php x \--</p>', trim($texy->toHtml("/--code php\nx\n\\--")));
});
