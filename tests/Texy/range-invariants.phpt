<?php declare(strict_types=1);

/**
 * Test: ranges of the parsed tree obey the invariants on every test source.
 *
 * Every node and modifier lies inside the source and inside its parent,
 * siblings follow the source order without overlapping, and only nodes
 * without a source of their own have no range.
 */

use Tester\Assert;
use Texy\{Node, Nodes, Range};

require __DIR__ . '/../bootstrap.php';


function contains(Range $outer, Range $inner): bool
{
	return $inner->offset >= $outer->offset
		&& $inner->offset + $inner->length <= $outer->offset + $outer->length;
}


function format(Range $range): string
{
	return "[$range->offset,$range->length]";
}


/**
 * Checks the node and its subtree; $outer is the range the node must lie in.
 * @return list<string>  violations
 */
function check(Node $node, Range $outer, string $path = '', ?Range $table = null): array
{
	$errors = [];
	$path .= '/' . (new ReflectionClass($node))->getShortName();
	$range = $node->range;

	if ($range !== null && !contains($outer, $range)) {
		$errors[] = "$path " . format($range) . ' lies outside ' . format($outer);
	} elseif ($range === null && !isSynthesized($node)) {
		$errors[] = "$path has no range";
	}

	$own = $range ?? $outer;
	$modifier = $node instanceof Texy\HasModifier ? $node->getModifier() : null;
	if ($modifier?->range !== null && !contains($own, $modifier->range)) {
		$errors[] = "$path modifier " . format($modifier->range) . ' lies outside ' . format($own);
	}

	foreach (get_object_vars($node) as $name => $part) {
		if ($part instanceof Range && $name !== 'range' && !contains($own, $part)) {
			$errors[] = "$path $name " . format($part) . ' lies outside ' . format($own);
		}
	}

	// a cell continued by ^| takes its content from the following rows too
	$table = $node instanceof Nodes\TableNode ? $range : $table;
	$inner = $node instanceof Nodes\TableCellNode && $range !== null && $table !== null
		? new Range($range->offset, $table->offset + $table->length - $range->offset)
		: $own;
	$previous = null;
	foreach ($node->getChildren() as $child) {
		if ($child->range !== null) {
			if ($previous !== null && $child->range->offset < $previous->offset + $previous->length) {
				$errors[] = "$path child " . format($child->range) . ' overlaps or precedes ' . format($previous);
			}
			$previous = $child->range;
		}
		$errors = [...$errors, ...check($child, $inner, $path, $table)];
	}

	return $errors;
}


/**
 * Nodes that have no source of their own: containers, and the padding of tables.
 */
function isSynthesized(Node $node): bool
{
	return $node instanceof Nodes\DocumentNode
		|| $node instanceof Nodes\ContentNode
		|| $node instanceof Nodes\TableCellNode // evens up a short row
		|| ($node instanceof Nodes\TextNode && $node->text === "\u{A0}"); // fills an empty cell
}


function checkSource(string $src): array
{
	$texy = new Texy\Texy;
	$src = $texy->preprocess($src);
	return check($texy->parse($src), new Range(0, strlen($src)));
}


test('test sources', function () {
	$files = glob(__DIR__ . '/sources/*.texy');
	Assert::count(31, $files);

	foreach ($files as $file) {
		Assert::same([], checkSource(file_get_contents($file)), basename($file));
	}
});


test('the code map of a code block points every line of the code at its source', function () {
	$src = "- item\n\n  /--code php\n    \$a = 1;\n      \$b = 2;\n  \\--\n\n> ```\n> x\n>   y\n> ```\n\n/--html\n<b>z</b>\n\\--";
	$blocks = Texy\NodeHelpers::find((new Texy\Texy)->parse($src), Nodes\CodeBlockNode::class);
	Assert::count(3, $blocks);

	foreach ($blocks as $block) {
		$local = 0;
		foreach (explode("\n", $block->code) as $line) {
			Assert::same($line, substr($src, $block->codeMap->toAbsolute($local), strlen($line)));
			$local += strlen($line) + 1;
		}
	}
});


/**
 * Ranges of the node and its subtree, the parts of the notation included, by node.
 * @return SplObjectStorage<object, array<string, ?Range>>
 */
function collectRanges(Node $node, SplObjectStorage $ranges = new SplObjectStorage): SplObjectStorage
{
	$ranges[$node] = array_filter(get_object_vars($node), fn($value) => $value instanceof Range) + ['range' => null];
	if (($modifier = $node instanceof Texy\HasModifier ? $node->getModifier() : null) !== null) {
		$ranges[$modifier] = ['range' => $modifier->range];
	}
	foreach ($node->getChildren() as $child) {
		collectRanges($child, $ranges);
	}

	return $ranges;
}


test('transform() keeps the range of every node, and a node it creates points at its origin', function () {
	foreach (glob(__DIR__ . '/sources/*.texy') as $file) {
		$texy = new Texy\Texy;
		$texy->headingModule->generateID = true;
		$src = $texy->preprocess(file_get_contents($file));
		$doc = $texy->parse($src);
		$before = collectRanges($doc);
		$texy->transform($doc);
		$after = collectRanges($doc);

		foreach ($after as $object) {
			$range = $after[$object]['range'];
			if (isset($before[$object])) {
				Assert::same($before[$object], $after[$object], basename($file));
			} elseif ($range !== null) {
				Assert::true(contains(new Range(0, strlen($src)), $range), basename($file));
			}
		}
	}
});


test('a list ends with its last item', function () {
	$texy = new Texy\Texy;
	$src = "- one\n- two\n\n  continued";
	$list = $texy->parse($src)->content->children[0];
	Assert::equal(new Range(0, strlen($src)), $list->range);
});


test('a table ends with its last line, a closing separator included', function () {
	$texy = new Texy\Texy;
	$src = "|----\n| a | b\n| c | d\n|----";
	$table = $texy->parse($src)->content->children[0];
	Assert::equal(new Range(0, strlen($src)), $table->range);
});


test('a cell modifier has a range only where it is written as one part', function () {
	$texy = new Texy\Texy;
	$src = "|.[a] x | y .[b]\n| z | w\n|.[c] u .[d]| v";
	$rows = $texy->parse($src)->content->children[0]->rows;
	$read = fn(Range $range) => substr($src, $range->offset, $range->length);

	Assert::same('[a]', $read($rows[0]->cells[0]->modifier->range));
	Assert::same('[b]', $read($rows[0]->cells[1]->modifier->range));
	Assert::null($rows[1]->cells[0]->modifier->range); // inherited from the column
	Assert::null($rows[2]->cells[0]->modifier->range); // two parts
	Assert::same([], checkSource($src));
});


test('code blocks parsed as markup map their content into the source', function () {
	$texy = new Texy\Texy;
	$src = "/--texysource\n  text **bold**\n\\--\n\n/--pre\n  <b>x</b>\n\\--\n\n/--html\n<i>y</i>\n\\--";
	$doc = $texy->parse($src);
	$read = fn(Node $node) => substr($src, $node->range->offset, $node->range->length);

	Assert::same('**bold**', $read(Texy\NodeHelpers::findFirst($doc, fn($node) => $node instanceof Nodes\PhraseNode)));
	Assert::same(['<b>', '</b>', '<i>', '</i>'], array_map($read, Texy\NodeHelpers::find($doc, Nodes\HtmlTagNode::class)));
	Assert::same([], checkSource($src));
});


test('a link records the syntax it is written in, its text and its target', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::LinkReference] = true;
	$src = "- \"quoted\":http://a\n- **strong**:b\n- [wiki | c]\n- [mark](d)\n- word:[e]\n- [ref]\n- see [* img.png *]:f";
	$read = fn(?Range $range) => $range === null ? null : substr($src, $range->offset, $range->length);

	Assert::same(
		[
			[Texy\Syntax::SpanQuotes, 'quoted', 'http://a'],
			[Texy\Syntax::Strong, 'strong', 'b'],
			[Texy\Syntax::WikiLink, 'wiki', 'c'],
			[Texy\Syntax::MarkdownLink, 'mark', 'd'],
			[Texy\Syntax::QuickLink, 'word', '[e]'],
			[Texy\Syntax::LinkReference, 'ref', 'ref'],
			[Texy\Syntax::Image, null, 'f'],
		],
		array_map(
			fn($link) => [$link->syntax, $read($link->textRange), $read($link->urlRange)],
			Texy\NodeHelpers::find($texy->parse($src), Nodes\LinkNode::class),
		),
	);
	Assert::same([], checkSource($src));
});
