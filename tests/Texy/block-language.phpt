<?php declare(strict_types=1);

/**
 * Test: /--language blocks - a block type that is not built in is read as a code block.
 */

use Tester\Assert;
use Texy\{Nodes, Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


/** @return list<Nodes\BlockNode> */
function blocksOf(string $source): array
{
	return (new Texy)->parse($source)->content->children;
}


test('an unknown type becomes a code block in that language', function () {
	[$node] = blocksOf("/--php\necho 1;\n\\--\n");

	Assert::type(Nodes\CodeBlockNode::class, $node);
	Assert::same('php', $node->language);
	Assert::same('echo 1;', $node->code);
});


test('content is verbatim, so typography does not touch it', function () {
	$texy = new Texy;
	Assert::same(
		"<pre class=\"language-neon\"><code>a: 'b'</code></pre>\n",
		$texy->toHtml("/--neon\na: 'b'\n\\--\n"),
	);
});


test('built-in types keep their own meaning', function () {
	$cases = [
		"/--code php\nx\n\\--\n" => Nodes\CodeBlockNode::class,
		"/--pre\nx\n\\--\n" => Nodes\PreformattedNode::class,
		"/--text\nx\n\\--\n" => Nodes\TextBlockNode::class,
		"/--html\nx\n\\--\n" => Nodes\HtmlBlockNode::class,
		"/--\nx\n\\--\n" => Nodes\PreformattedNode::class,
	];

	foreach ($cases as $source => $class) {
		[$node] = blocksOf($source);
		Assert::same($class, $node::class, $source);
	}
	Assert::same('php', blocksOf("/--code php\nx\n\\--\n")[0]->language);
	Assert::same(Syntax::BlockDefault, blocksOf("/--\nx\n\\--\n")[0]->syntax);
});


test('the opening tag is case-insensitive, so a built-in type keeps its meaning', function () {
	// the /-- pattern matches case-insensitively; without lowercasing the type,
	// /--PRE would fall through and become a code block in a language called "PRE"
	foreach (['/--PRE', '/--pre'] as $head) {
		[$node] = blocksOf("$head\nx\n\\--\n");
		Assert::type(Nodes\PreformattedNode::class, $node, $head);
	}

	[$node] = blocksOf("/--CODE php\nx\n\\--\n");
	Assert::type(Nodes\CodeBlockNode::class, $node);
	Assert::same('php', $node->language);
});


test('only the first word names the language', function () {
	[$node] = blocksOf("/--php 8.3\nx\n\\--\n");

	Assert::type(Nodes\CodeBlockNode::class, $node);
	Assert::same('php', $node->language);
});


test('a modifier still applies', function () {
	[$node] = blocksOf("/--php .[highlight]\nx\n\\--\n");

	Assert::same('php', $node->language);
	Assert::same(['highlight' => true], $node->modifier->classes);
});


test('what does not look like a language name is still refused', function () {
	// a malformed modifier must not be mistaken for a language; the block degrades to text
	$texy = new Texy;
	Assert::contains('<p>/--.[texy]', $texy->toHtml("/--.[texy]\nx\n\\--\n"));
});
