<?php declare(strict_types=1);

use Tester\Assert;
use Texy\Nodes\{HeadingNode, HeadingType};

require __DIR__ . '/../bootstrap.php';


test('getHeadings - headings are AST facts', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("First Heading\n=============\n\nSecond Heading\n--------------");
	$texy->transform($document);

	$headings = $document->getHeadings();
	Assert::count(2, $headings);
	Assert::same('First Heading', $headings[0]->getTitle());
	Assert::same(1, $headings[0]->level);
	Assert::same('Second Heading', $headings[1]->getTitle());
	Assert::same(2, $headings[1]->level);
});


test('getHeadings - skips texysource sections', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title\n=====\n\n/--texysource\nInner\n=====\n\\--");
	$texy->transform($document);
	Assert::count(2, $document->find(HeadingNode::class)); // the demo heading is in the tree

	$headings = $document->getHeadings();
	Assert::count(1, $headings);
	Assert::same('Title', $headings[0]->getTitle());
});


test('a heading of a bare URL or e-mail takes its title and ID from it', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$document = $texy->parse("https://example.com/foo\n=======================\n\njana@example.com\n================");
	$texy->transform($document);

	[$url, $email] = $document->getHeadings();
	Assert::same('https://example.com/foo', $url->getTitle());
	Assert::same('toc-https-example-com-foo', $url->id);
	Assert::same('jana@example.com', $email->getTitle());
	Assert::same('toc-jana-example-com', $email->id);
});


test('getHeadings - no headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse('Just a paragraph.');
	$texy->transform($document);

	Assert::same([], $document->getHeadings());
});


test('{toc:} style overrides the title and the ID slug', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$document = $texy->parse("Title .{toc: custom}\n=====");
	$texy->transform($document);

	$heading = $document->getHeadings()[0];
	Assert::same('custom', $heading->getTitle());
	Assert::same('toc-custom', $heading->id);
});


test('a title override set before the heading pass is kept', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$texy->addPass(function (Texy\Nodes\DocumentNode $document) {
		$document->getHeadings()[0]->titleOverride = 'mine';
	}, Texy\Stage::Syntax);
	$document = $texy->parse("Title .{toc: custom}\n=====");
	$texy->transform($document);

	$heading = $document->getHeadings()[0];
	Assert::same('mine', $heading->getTitle());
	Assert::same('toc-mine', $heading->id);
	Assert::false(isset($heading->modifier->styles['toc']));
});


test('notexy in the title and the ID slug stays verbatim', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$document = $texy->parse("''-w | --watch <path>''\n--------");
	$texy->transform($document);

	$heading = $document->getHeadings()[0];
	Assert::same('-w | --watch <path>', $heading->getTitle());
	Assert::same('toc-w-watch-path', $heading->id);
});


test('{toc:} is transport syntax - never reaches the output', function () {
	$texy = new Texy\Texy;
	$html = $texy->toHtml("Title .{toc: custom}\n=====");

	Assert::notContains('toc', $html);
	Assert::contains('>Title</h1>', $html);
});


test('{toc:} applies without generateID', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title .{toc: custom}\n=====");
	$texy->transform($document);

	Assert::same('custom', $document->getHeadings()[0]->getTitle());
});


test('Fixed balancing - underline character maps to a fixed level', function () {
	$texy = new Texy\Texy;
	$texy->config->headingBalancing = Texy\HeadingBalancing::Fixed;
	$document = $texy->parse("A\n###\n\nB\n***\n\nC\n===\n\nD\n---");
	$texy->transform($document);

	// level = $levels[$char] + $top
	Assert::same([1, 2, 3, 4], array_map(fn($h) => $h->level, $document->getHeadings()));
});


test('Fixed balancing - respects $top', function () {
	$texy = new Texy\Texy;
	$texy->config->headingBalancing = Texy\HeadingBalancing::Fixed;
	$texy->config->headingTop = 3;
	$document = $texy->parse("A\n###\n\nB\n***");
	$texy->transform($document);

	Assert::same([3, 4], array_map(fn($h) => $h->level, $document->getHeadings()));
});


test('Dynamic balancing - the most important heading becomes $top', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("A\n***\n\nB\n===\n\n### C ###");
	$texy->transform($document);

	Assert::same([1, 2, 1], array_map(fn($h) => $h->level, $document->getHeadings()));
});


test('explicit ID wins and reserves the name', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$document = $texy->parse("Title .[#custom]\n=====\n\nTitle\n=====\n\nTitle\n=====");
	$texy->transform($document);

	// the explicit one keeps its ID, generated ones dedupe among themselves
	Assert::same(
		['custom', 'toc-title', 'toc-title-2'],
		array_map(fn($h) => $h->id, $document->getHeadings()),
	);
});


test('explicit ID later in the document is not taken by a generated one', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$document = $texy->parse("Title\n=====\n\nOther .[#toc-title]\n===================\n\nTitle\n=====");
	$texy->transform($document);

	Assert::same(
		['toc-title-2', 'toc-title', 'toc-title-3'],
		array_map(fn($h) => $h->id, $document->getHeadings()),
	);
});


test('an explicit ID the policy rejects is replaced by a generated one', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$texy->htmlPolicy->allowedClasses = ['#allowed'];
	$document = $texy->parse("Title .[#my]\n=====\n\nOther .[#allowed]\n=====");
	$texy->transform($document);

	Assert::same(['toc-title', 'allowed'], array_map(fn($h) => $h->id, $document->getHeadings()));
	Assert::same(
		"<h1 id=\"toc-title\">Title</h1>\n\n<h1 id=\"allowed\">Other</h1>\n",
		$texy->renderHtml($document),
	);

	$texy->htmlPolicy->allowedClasses = false;
	Assert::same("<h1 id=\"toc-my\">My</h1>\n", $texy->toHtml("My .[#my]\n========="));
});


test('two parses in a row - results do not leak', function () {
	$texy = new Texy\Texy;
	$texy->toHtml("First\n=====");
	$document = $texy->parse("Second\n======");
	$texy->transform($document);

	Assert::same('Second', $document->getHeadings()[0]->getTitle());
});


test('outline structure - underlined headings', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("Title\n=====\n\nSubtitle\n--------");
	$texy->transform($document);
	$headings = $document->getHeadings();

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
	$headings = $document->getHeadings();

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
	$html = $texy->toHtml("Title\n=====");

	Assert::notContains('id=', $html);
});


test('generateID = true', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$html = $texy->toHtml("Title\n=====");

	Assert::contains('id="toc-title"', $html);
});


test('a soft hyphen does not split the generated ID', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	Assert::contains('id="toc-slozeni"', $texy->toHtml("Slo&shy;žení\n====="));
});


test('a zero width space does not split the generated ID', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	Assert::contains('id="toc-ab"', $texy->toHtml("a\u{200B}b\n====="));
});


test('custom idPrefix', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$texy->config->headingIdPrefix = 'h-';
	$html = $texy->toHtml("Title\n=====");

	Assert::contains('id="h-title"', $html);
});


test('ID collision - duplicate headings', function () {
	$texy = new Texy\Texy;
	$texy->config->headingIds = true;
	$html = $texy->toHtml("Title\n=====\n\nTitle\n=====\n\nTitle\n=====");

	Assert::contains('id="toc-title"', $html);
	Assert::contains('id="toc-title-2"', $html);
	Assert::contains('id="toc-title-3"', $html);
});
