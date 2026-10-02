<?php declare(strict_types=1);

/**
 * Test: Texy::renderTexy() - the syntax tree printed back as Texy source
 */

use Tester\Assert;
use Texy\{Modifier, Node, Nodes, Range, Syntax, Texy};
use Texy\Output\Texy\Renderer as TexyRenderer;
use Texy\Parsing\OffsetMap;

require __DIR__ . '/../bootstrap.php';


/** The tree without positions and written forms; adjacent texts are one text and an empty modifier is none. */
function shape(mixed $value): mixed
{
	if ($value instanceof Node) {
		$res = ['@' => $value::class];
		foreach (get_object_vars($value) as $name => $item) {
			if (!$item instanceof Range && !$item instanceof OffsetMap && !in_array($name, ['range', 'meta', 'source'], true)) {
				$res[$name] = shape($item);
			}
		}

		return $res;

	} elseif ($value instanceof Modifier) {
		$res = get_object_vars($value);
		unset($res['range']);
		return array_filter($res, fn($item) => $item !== null && $item !== []) ? $res : null;

	} elseif (is_array($value)) {
		$res = [];
		foreach ($value as $key => $item) {
			if ($item instanceof Nodes\TextNode && ($res[array_key_last($res) ?? 0]['@'] ?? null) === Nodes\TextNode::class) {
				$res[array_key_last($res)]['text'] .= $item->text;
			} else {
				$res[$key] = shape($item);
			}
		}

		return array_is_list($value) ? array_values($res) : $res;
	}

	return $value instanceof UnitEnum ? $value->name : $value;
}


function printTexy(string $source): string
{
	$texy = new Texy;
	return $texy->renderTexy($texy->parse($source));
}


function printParagraph(Texy $texy, Nodes\InlineNode ...$children): string
{
	$paragraph = new Nodes\ParagraphNode(new Nodes\ContentNode($children));
	return $texy->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode([$paragraph])));
}


test('parsing the printed source gives the same tree, and printing it again the same source', function () {
	$skipped = [
		'formatter-linewrap-alt.texy', // lines of 180 000 asterisks exceed the JIT stack once the image is printed spaced
		'table.texy', // cells continued over more lines than they span rows
	];
	foreach (glob(__DIR__ . '/sources/*.texy') as $file) {
		if (in_array(basename($file), $skipped, true)) {
			continue;
		}

		$texy = new Texy;
		$document = $texy->parse(file_get_contents($file));
		$printed = $texy->renderTexy($document);
		$reparsed = $texy->parse($printed);
		Assert::same(json_encode(shape($document)), json_encode(shape($reparsed)), basename($file));
		Assert::same($printed, $texy->renderTexy($reparsed), basename($file));
	}
});


test('the notation a node records is kept', function () {
	$source = <<<'X'
		Underlined
		**********

		## Surrounded ##

		"quoted":http://a.cz, [wiki|http://b.cz], [markdown](http://c.cz) and **strong**:http://d.cz

		`code`:http://e.cz, `code`:[http://f.cz], `code .[bar]`:[ref] and `code`:[*image*]

		* first
		* second

		3) third
		4) fourth
		X;
	$source = normalizeNewlines($source);
	Assert::same($source . "\n", printTexy($source));
});


test('canonical form of what records no variant', function () {
	Assert::same(
		"Title .[main]\n=============\n\n|------------------------------\n| x | y |\n|------------------------------\n| 1 | 2 |\n",
		printTexy("Title  .[main]\n===\n\n| x | y\n|---\n| 1 | 2"),
	);

	$item = fn($text) => new Nodes\ListItemNode(new Nodes\ContentNode([new Nodes\TextNode($text)]));
	$list = new Nodes\ListNode([$item('a'), $item('b')], Nodes\ListType::UpperRoman);
	$heading = new Nodes\HeadingNode(new Nodes\ContentNode([new Nodes\TextNode('Synthesized')]), 2, Nodes\HeadingType::Underlined);
	Assert::same(
		"Synthesized\n===========\n\nI) a\nII) b\n",
		(new Texy)->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode([$heading, $list]))),
	);
});


test('text that would read as syntax is escaped', function () {
	Assert::same("&lt;b&gt; \\*not strong\\* &amp;amp;\n", printTexy('&lt;b&gt; \*not strong\* &amp;amp;'));
	Assert::same("&#45; not a list\n", printTexy(' - not a list'));

	$paragraph = new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\TextNode('<b> &amp;')]));
	Assert::same("&lt;b> &amp;amp;\n", (new Texy)->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode([$paragraph]))));
});


test('a line of a changed paragraph that would read as a block continues the previous one', function () {
	$image = new Nodes\ImageNode('a.png');
	$cases = [
		"foo - bar\n" => [new Nodes\TextNode("foo\n- bar")],
		"foo\nbar 1. baz\nqux\n" => [new Nodes\TextNode("foo\nbar\n1. baz\nqux")],
		"foo 1. bar\n" => [new Nodes\TextNode("foo\n"), new Nodes\TextNode('1'), new Nodes\TextNode('. bar')],
		"foo /--\n" => [new Nodes\TextNode("foo\n/--")],
		"foo [* a.png *]\n" => [new Nodes\TextNode("foo\n"), $image],
		"foo [* a.png *]\nbar\n" => [new Nodes\TextNode("foo\n"), $image, new Nodes\TextNode("\nbar")],
		"foo\n[* a.png *] bar\n" => [new Nodes\TextNode("foo\n"), $image, new Nodes\TextNode(' bar')],
		"[* a.png *] foo\n" => [$image, new Nodes\TextNode("\nfoo")],
		"[* a.png *] foo\nbar\n" => [$image, new Nodes\TextNode("\nfoo\nbar")],
		" [* a.png *]\n foo\n" => [$image, new Nodes\LineBreakNode, new Nodes\TextNode('foo')],
		"&#91;* a.png *]\n" => [new Nodes\TextNode('[* a.png *]')],
		"foo ---\n" => [new Nodes\TextNode("foo\n---")],
		"&#124; a |--- | b\n" => [new Nodes\TextNode("| a\n|---\n| b")],
		"foo * - x\n" => [new Nodes\TextNode("foo\n*\n- x")],
		"foo 1. 2) x\n" => [new Nodes\TextNode("foo\n1.\n2) x")],
		"&#73;. 2) x\n" => [new Nodes\TextNode("I.\n2) x")],
	];
	foreach ($cases as $expected => $children) {
		$texy = new Texy;
		$printed = printParagraph($texy, ...$children);
		Assert::same($expected, $printed);

		$blocks = $texy->parse($printed)->content->children;
		Assert::count(1, $blocks, $expected);
		Assert::type(Nodes\ParagraphNode::class, $blocks[0], $expected);
	}

	Assert::same(" [* a.png *]\n foo\n", printTexy(" [* a.png *]\n foo"));
	Assert::same("&#49;. .<>\n foo\n", printTexy("1.\n foo .<>"));
});


test('an image on the first line of a paragraph inside a list item or a quote goes on with the next line', function () {
	$paragraph = new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\ImageNode('a.png'), new Nodes\TextNode("\nfoo")]));
	$cases = [
		"- [* a.png *] foo\n" => new Nodes\ListNode(
			[new Nodes\ListItemNode(new Nodes\ContentNode([$paragraph]))],
			Nodes\ListType::Unordered,
		),
		"> [* a.png *] foo\n" => new Nodes\BlockQuoteNode(new Nodes\ContentNode([$paragraph])),
	];
	foreach ($cases as $expected => $block) {
		$texy = new Texy;
		$printed = $texy->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode([$block])));
		Assert::same($expected, $printed);

		$inner = $texy->parse($printed)->content->children[0];
		$content = $inner instanceof Nodes\ListNode ? $inner->items[0]->content : $inner->content;
		Assert::count(1, $content->children, $expected);
		Assert::type(Nodes\ParagraphNode::class, $content->children[0], $expected);
	}
});


test('what reads as a block is decided by the given Texy', function () {
	$text = fn(string $text) => new Nodes\TextNode($text);
	Assert::same("2.5 million\n", printParagraph(new Texy, $text('2.5 million')));
	Assert::same("2. bar\n", printParagraph(new Texy, $text('2. bar')));
	Assert::same("foo\n2. bar\n", printParagraph(new Texy, $text("foo\n2. bar")));
	Assert::same("foo\n@@ bar\n", printParagraph(new Texy, $text("foo\n@@ bar")));

	$custom = new Texy;
	$custom->addBlockSyntax('custom', '~^@@ .*$~', fn() => new Nodes\HorizontalRuleNode('-'));
	Assert::same("foo @@ bar\n", printParagraph($custom, $text("foo\n@@ bar")));
	Assert::same("&#64;@ bar\n", printParagraph($custom, $text('@@ bar')));

	$withoutLists = new Texy;
	$withoutLists->allowed[Syntax::List] = false;
	Assert::same("foo\n- bar\n", printParagraph($withoutLists, $text("foo\n- bar")));
});


test('text whose block a handler refuses only by its neighbours is escaped', function () {
	$paragraph = fn(string $text) => new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\TextNode($text)]));
	$cases = [
		"&#49;. a\n\n2. b\n" => ['1. a', '2. b'],
		"&#73;. a\n\nII. b\n" => ['I. a', 'II. b'],
		"&#47;--.[a] b\n\n/--\nc\n\\--\n" => ['/--.[a] b', new Nodes\CodeBlockNode('default', 'c')],
	];
	foreach ($cases as $expected => $blocks) {
		$texy = new Texy;
		$blocks = array_map(fn($block) => is_string($block) ? $paragraph($block) : $block, $blocks);
		$printed = $texy->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode($blocks)));
		Assert::same($expected, $printed);
		Assert::count(2, $texy->parse($printed)->content->children, $expected);
	}
});


test('a line that would close the block around the paragraph is escaped or continues the previous one', function () {
	$texy = new Texy;
	$section = fn(string $text) => new Nodes\DocumentNode(new Nodes\ContentNode([
		new Nodes\SectionNode(new Nodes\ContentNode([new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\TextNode($text)]))]), 'div'),
	]));
	Assert::same("/--div\n&#92;-- foo\n\\--\n", $texy->renderTexy($section('\-- foo')));
	Assert::same("/--div\nfoo \\-- bar\n\\--\n", $texy->renderTexy($section("foo\n\\-- bar")));
});


test('a hard break after the first line of a list item survives text that starts like a block', function () {
	$texy = new Texy;
	$paragraph = new Nodes\ParagraphNode(new Nodes\ContentNode([
		new Nodes\TextNode('| a b'),
		new Nodes\LineBreakNode,
		new Nodes\TextNode('c'),
	]));
	$list = new Nodes\ListNode([new Nodes\ListItemNode(new Nodes\ContentNode([$paragraph]))], Nodes\ListType::Unordered);
	$printed = $texy->renderTexy(new Nodes\DocumentNode(new Nodes\ContentNode([$list])));
	Assert::same("- &#124; a\n  b\n   c\n", $printed);

	$children = $texy->parse($printed)->content->children[0]->items[0]->content->children[0]->content->children;
	Assert::count(1, array_filter($children, fn($child) => $child instanceof Nodes\LineBreakNode));
});


test('a node of an extension needs a renderer of its own', function () {
	$node = new class extends Nodes\InlineNode {
	};
	$document = new Nodes\DocumentNode(new Nodes\ContentNode([new Nodes\ParagraphNode(new Nodes\ContentNode([$node]))]));

	$renderer = new TexyRenderer(new Texy);
	Assert::exception(
		fn() => $renderer->render($document),
		LogicException::class,
		'No renderer prints node class %a% as Texy; add one with addRenderer().',
	);

	$renderer->addRenderer(fn(Nodes\InlineNode $node) => '{{custom}}');
	Assert::same("{{custom}}\n", $renderer->render($document));
});
