<?php declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\HeadingNode;
use Texy\Nodes\HeadingType;

require __DIR__ . '/../bootstrap.php';


test('collectFrom - headings are AST facts', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("First Heading\n=============\n\nSecond Heading\n--------------");

	$headings = HeadingNode::collectFrom($document);
	Assert::count(2, $headings);
	Assert::same('First Heading', $headings[0]->tocTitle);
	Assert::same(1, $headings[0]->level);
	Assert::same('Second Heading', $headings[1]->tocTitle);
	Assert::same(2, $headings[1]->level);
});


test('collectFrom - skips texysource sections', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title\n=====\n\n/--texysource\nInner\n=====\n\\--");

	$headings = HeadingNode::collectFrom($document);
	Assert::count(1, $headings);
	Assert::same('Title', $headings[0]->tocTitle);
});


test('collectFrom - no headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse('Just a paragraph.');

	Assert::same([], HeadingNode::collectFrom($document));
});


test('{toc:} style overrides the title and the ID slug', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$document = $texy->parse("Title .{toc: custom}\n=====");

	$heading = HeadingNode::collectFrom($document)[0];
	Assert::same('custom', $heading->tocTitle);
	Assert::same('toc-custom', $heading->modifier->id);

	// the deprecated bridge reports the custom title too
	Assert::same('custom', $texy->headingModule->title);
	Assert::same('custom', $texy->headingModule->TOC[0]['title']);
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

	Assert::same('custom', HeadingNode::collectFrom($document)[0]->tocTitle);
});


test('Fixed balancing - underline character maps to a fixed level', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;
	$document = $texy->parse("A\n###\n\nB\n***\n\nC\n===\n\nD\n---");

	// level = $levels[$char] + $top
	Assert::same([1, 2, 3, 4], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('Fixed balancing - respects $top', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;
	$texy->headingModule->top = 3;
	$document = $texy->parse("A\n###\n\nB\n***");

	Assert::same([3, 4], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('Dynamic balancing - the most important heading becomes $top', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("A\n***\n\nB\n===\n\n### C ###");

	Assert::same([1, 2, 1], array_map(fn($h) => $h->level, HeadingNode::collectFrom($document)));
});


test('explicit ID wins and reserves the name', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$document = $texy->parse("Title .[#custom]\n=====\n\nTitle\n=====\n\nTitle\n=====");

	// the explicit one keeps its ID, generated ones dedupe among themselves
	Assert::same(
		['custom', 'toc-title', 'toc-title-2'],
		array_map(fn($h) => $h->modifier->id, HeadingNode::collectFrom($document)),
	);
});


test('two parses in a row - results do not leak', function () {
	$texy = new Texy\Texy;
	$texy->process("First\n=====");
	$document = $texy->parse("Second\n======");

	Assert::same('Second', HeadingNode::collectFrom($document)[0]->tocTitle);
	Assert::same('Second', $texy->headingModule->title);
	Assert::count(1, $texy->headingModule->TOC);
});


test('title property - first heading', function () {
	$texy = new Texy\Texy;
	$texy->process("First Heading\n=============\n\nSecond Heading\n--------------");

	Assert::same('First Heading', $texy->headingModule->title);
});


test('title property - no headings', function () {
	$texy = new Texy\Texy;
	$texy->process('Just a paragraph.');

	Assert::null($texy->headingModule->title);
});


test('TOC structure - underlined headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->process("Title\n=====\n\nSubtitle\n--------");

	Assert::count(2, $texy->headingModule->TOC);

	// First heading
	Assert::same('Title', $texy->headingModule->TOC[0]['title']);
	Assert::same(1, $texy->headingModule->TOC[0]['node']->level);
	Assert::same(HeadingType::Underlined, $texy->headingModule->TOC[0]['node']->type);

	// Second heading
	Assert::same('Subtitle', $texy->headingModule->TOC[1]['title']);
	Assert::same(2, $texy->headingModule->TOC[1]['node']->level);
	Assert::same(HeadingType::Underlined, $texy->headingModule->TOC[1]['node']->type);
});


test('TOC structure - surrounded headings', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->generateID = true;
	$texy->process("### Title ###\n\n## Subtitle ##");

	Assert::count(2, $texy->headingModule->TOC);

	// First heading (### = h1, more # = higher level)
	Assert::same('Title', $texy->headingModule->TOC[0]['title']);
	Assert::same(1, $texy->headingModule->TOC[0]['node']->level);
	Assert::same(HeadingType::Surrounded, $texy->headingModule->TOC[0]['node']->type);

	// Second heading (## = h2)
	Assert::same('Subtitle', $texy->headingModule->TOC[1]['title']);
	Assert::same(2, $texy->headingModule->TOC[1]['node']->level);
	Assert::same(HeadingType::Surrounded, $texy->headingModule->TOC[1]['node']->type);
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
