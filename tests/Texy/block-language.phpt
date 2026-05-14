<?php declare(strict_types=1);

/**
 * Test: /--language blocks - a block type that is not built in is read as a code block.
 */

use Tester\Assert;
use Texy\{Nodes, Texy};

require __DIR__ . '/../bootstrap.php';


/** @return list<Nodes\BlockNode> */
function blocksOf(string $source): array
{
	return (new Texy)->parse($source)->content->children;
}


test('an unknown type becomes a code block in that language', function () {
	[$node] = blocksOf("/--php\necho 1;\n\\--\n");

	Assert::type(Nodes\CodeBlockNode::class, $node);
	Assert::same('code', $node->type);
	Assert::same('php', $node->language);
	Assert::same('echo 1;', $node->code);
});


test('content is verbatim, so typography does not touch it', function () {
	$texy = new Texy;
	Assert::same(
		"<pre class=\"neon\"><code>a: 'b'</code></pre>\n",
		$texy->process("/--neon\na: 'b'\n\\--\n"),
	);
});


test('built-in types keep their own meaning', function () {
	$cases = [
		"/--code php\nx\n\\--\n" => ['code', 'php'],
		"/--pre\nx\n\\--\n" => ['pre', null],
		"/--text\nx\n\\--\n" => ['text', null],
		"/--\nx\n\\--\n" => ['default', null],
	];

	foreach ($cases as $source => [$type, $language]) {
		[$node] = blocksOf($source);
		Assert::type(Nodes\CodeBlockNode::class, $node);
		Assert::same($type, $node->type, $source);
		Assert::same($language, $node->language, $source);
	}
});


test('the opening tag is case-insensitive, so a built-in type keeps its meaning', function () {
	// the /-- pattern matches case-insensitively; without lowercasing the type,
	// /--PRE would fall through and become a code block in a language called "PRE"
	foreach (['/--PRE' => 'pre', '/--pre' => 'pre'] as $head => $type) {
		[$node] = blocksOf("$head\nx\n\\--\n");
		Assert::same($type, $node->type, $head);
		Assert::null($node->language, $head);
	}

	[$node] = blocksOf("/--CODE php\nx\n\\--\n");
	Assert::same('code', $node->type);
	Assert::same('php', $node->language);
});


test('only the first word names the language', function () {
	[$node] = blocksOf("/--php 8.3\nx\n\\--\n");

	Assert::same('code', $node->type);
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
	Assert::contains('<p>/--.[texy]', $texy->process("/--.[texy]\nx\n\\--\n"));
});
