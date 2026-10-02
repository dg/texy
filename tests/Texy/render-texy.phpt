<?php declare(strict_types=1);

/**
 * Test: Texy::renderTexy() - the syntax tree printed back as Texy source
 */

use Tester\Assert;
use Texy\{Modifier, Node, Nodes, Range, Texy};
use Texy\Output\Texy\Renderer as TexyRenderer;
use Texy\Parsing\OffsetMap;

require __DIR__ . '/../bootstrap.php';


/** The tree without positions; adjacent texts are one text and an empty modifier is none. */
function shape(mixed $value): mixed
{
	if ($value instanceof Node) {
		$res = ['@' => $value::class];
		foreach (get_object_vars($value) as $name => $item) {
			if (!$item instanceof Range && !$item instanceof OffsetMap && !in_array($name, ['range', 'meta'], true)) {
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


test('a node of an extension needs a renderer of its own', function () {
	$node = new class extends Nodes\InlineNode {
	};
	$document = new Nodes\DocumentNode(new Nodes\ContentNode([new Nodes\ParagraphNode(new Nodes\ContentNode([$node]))]));

	$renderer = new TexyRenderer;
	Assert::exception(
		fn() => $renderer->render($document),
		LogicException::class,
		'No renderer prints node class %a% as Texy; add one with addRenderer().',
	);

	$renderer->addRenderer(fn(Nodes\InlineNode $node) => '{{custom}}');
	Assert::same("{{custom}}\n", $renderer->render($document));
});
