<?php declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\{HeadingNode, HeadingType};

require __DIR__ . '/../bootstrap.php';


test('collectFrom - headings are AST facts', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("First Heading\n=============\n\nSecond Heading\n--------------");
	$texy->transform($document);

	$headings = HeadingNode::collectFrom($document);
	Assert::count(2, $headings);
	Assert::same('First Heading', $headings[0]->getTitle());
	Assert::same(1, $headings[0]->level);
	Assert::same('Second Heading', $headings[1]->getTitle());
	Assert::same(2, $headings[1]->level);
});


test('collectFrom - skips texysource sections', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title\n=====\n\n/--texysource\nInner\n=====\n\\--");
	$texy->transform($document);
	Assert::count(2, Texy\NodeHelpers::find($document, HeadingNode::class)); // the demo heading is in the tree

	$headings = HeadingNode::collectFrom($document);
	Assert::count(1, $headings);
	Assert::same('Title', $headings[0]->getTitle());
});


test('collectFrom - no headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse('Just a paragraph.');
	$texy->transform($document);

	Assert::same([], HeadingNode::collectFrom($document));
});


test('{toc:} style overrides the title and the ID slug', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$document = $texy->parse("Title .{toc: custom}\n=====");
	$texy->transform($document);

	$heading = HeadingNode::collectFrom($document)[0];
	Assert::same('custom', $heading->getTitle());
	Assert::same('toc-custom', $heading->id);
});


test('{toc:} is transport syntax - never reaches the output', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("Title .{toc: custom}\n=====");

	Assert::notContains('toc', $html);
	Assert::contains('>Title</h1>', $html);
});


test('{toc:} applies without generateID', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title .{toc: custom}\n=====");
	$texy->transform($document);

	Assert::same('custom', HeadingNode::collectFrom($document)[0]->getTitle());
});


test('Fixed balancing - underline character maps to a fixed level', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;
	$document = $texy->parse("A\n###\n\nB\n***\n\nC\n===\n\nD\n---");
	$texy->transform($document);

	// level = $levels[$char] + $top
	Assert::same([1, 2, 3, 4], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('Fixed balancing - respects $top', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;
	$texy->headingModule->top = 3;
	$document = $texy->parse("A\n###\n\nB\n***");
	$texy->transform($document);

	Assert::same([3, 4], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('Dynamic balancing - the most important heading becomes $top', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("A\n***\n\nB\n===\n\n### C ###");
	$texy->transform($document);

	Assert::same([1, 2, 1], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('explicit ID wins and reserves the name', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$document = $texy->parse("Title .[#custom]\n=====\n\nTitle\n=====\n\nTitle\n=====");
	$texy->transform($document);

	// the explicit one keeps its ID, generated ones dedupe among themselves
	Assert::same(
		['custom', 'toc-title', 'toc-title-2'],
		array_map(fn($h) => $h->id, HeadingNode::collectFrom($document)),
	);
});


test('explicit ID later in the document is not taken by a generated one', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$document = $texy->parse("Title\n=====\n\nOther .[#toc-title]\n===================\n\nTitle\n=====");
	$texy->transform($document);

	Assert::same(
		['toc-title-2', 'toc-title', 'toc-title-3'],
		array_map(fn($h) => $h->id, HeadingNode::collectFrom($document)),
	);
});


test('two parses in a row - results do not leak', function () {
	$texy = new Texy\Texy;
	$texy->process("First\n=====");
	$document = $texy->parse("Second\n======");
	$texy->transform($document);

	Assert::same('Second', HeadingNode::collectFrom($document)[0]->getTitle());
});


test('outline structure - underlined headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title\n=====\n\nSubtitle\n--------");
	$texy->transform($document);
	$headings = HeadingNode::collectFrom($document);

	Assert::count(2, $headings);
	Assert::same('Title', $headings[0]->getTitle());
	Assert::same(1, $headings[0]->level);
	Assert::same(HeadingType::Underlined, $headings[0]->type);
	Assert::same('Subtitle', $headings[1]->getTitle());
	Assert::same(2, $headings[1]->level);
	Assert::same(HeadingType::Underlined, $headings[1]->type);
});


test('outline structure - surrounded headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("### Title ###\n\n## Subtitle ##");
	$texy->transform($document);
	$headings = HeadingNode::collectFrom($document);

	Assert::count(2, $headings);
	// ### = h1, more # = higher level
	Assert::same('Title', $headings[0]->getTitle());
	Assert::same(1, $headings[0]->level);
	Assert::same(HeadingType::Surrounded, $headings[0]->type);
	Assert::same('Subtitle', $headings[1]->getTitle());
	Assert::same(2, $headings[1]->level);
	Assert::same(HeadingType::Surrounded, $headings[1]->type);
});


test('generateID = false (default)', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("Title\n=====");

	Assert::notContains('id=', $html);
});


test('generateID = true', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$html = $texy->process("Title\n=====");

	Assert::contains('id="toc-title"', $html);
});


test('custom idPrefix', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->headingModule->idPrefix = 'h-';
	$html = $texy->process("Title\n=====");

	Assert::contains('id="h-title"', $html);
});


test('ID collision - duplicate headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$html = $texy->process("Title\n=====\n\nTitle\n=====\n\nTitle\n=====");

	Assert::contains('id="toc-title"', $html);
	Assert::contains('id="toc-title-2"', $html);
	Assert::contains('id="toc-title-3"', $html);
});
