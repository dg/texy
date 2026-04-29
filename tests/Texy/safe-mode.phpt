<?php declare(strict_types=1);

/**
 * Test: Configurator::safeMode()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy(): Texy\Texy
{
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	return $texy;
}


test('images are disabled', function () {
	Assert::match(
		'<p>[* a.jpg *]</p>',
		createTexy()->toHtml('[* a.jpg *]'),
	);
});


test('figures are disabled', function () {
	Assert::match(
		'<p>[* a.jpg *] *** caption</p>',
		createTexy()->toHtml('[* a.jpg *] *** caption'),
	);
});


test('image definitions are disabled', function () {
	Assert::match(
		<<<'XX'
			<p>[<em>x</em>]: a.jpg</p>

			<p>[<em>x</em>] *** caption</p>

			XX,
		createTexy()->toHtml("[*x*]: a.jpg\n\n[*x*] *** caption"),
	);
});


test('images and figures can be kept, their definitions stay disabled', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy, images: true);
	Assert::same(
		"<p>x <img src=\"http://a.cz/a.jpg\" alt=\"\"></p>\n\n<figure><img src=\"http://a.cz/b.jpg\" alt=\"\">\n\t<figcaption>caption</figcaption>\n</figure>\n\n<p><img src=\"x\" alt=\"\">: a.jpg</p>",
		trim($texy->toHtml("x [* http://a.cz/a.jpg *]\n\n[* http://a.cz/b.jpg *] *** caption\n\n[*x*]: a.jpg")),
	);
	Assert::same('<p>[* javascript:x *]</p>', trim($texy->toHtml('[* javascript:x *]')));
});


test('a tab or newline does not hide a forbidden scheme', function () {
	Assert::match('<p>a</p>', createTexy()->toHtml("\"a\":[java\tscript:alert(1)]"));
	Assert::match('<p>a</p>', createTexy()->toHtml("[a|java\tscript:alert(1)]"));
	Assert::notContains('<a', createTexy()->toHtml('<a href="jav&Tab;ascript:alert(1)">x</a>'));
	Assert::notContains('<a', createTexy()->toHtml('<a href="jav&#10;ascript:alert(1)">x</a>'));
});


test('the link of a code phrase obeys the URL policy', function () {
	Assert::match(
		'<p><a href="http://a.cz" rel="nofollow"><code>a</code></a></p>',
		createTexy()->toHtml('`a`:http://a.cz'),
	);
	Assert::match(
		'<p><code>a</code></p>',
		createTexy()->toHtml('`a`:[javascript:alert(1)]'),
	);
});


test('markup written by entities in an autolinked address stays text', function () {
	$texy = createTexy();
	Assert::notContains('<b>', $texy->toHtml('https://x.cz/&lt;b&gt;a'));
	$texy->htmlOutput->shortenUrls = false;
	Assert::contains('>https://x.cz/&lt;b&gt;a</a>', $texy->toHtml('https://x.cz/&lt;b&gt;a'));
});


test('the address of an autolink built by code is escaped', function () {
	$texy = createTexy();
	$doc = new Texy\Nodes\DocumentNode(new Texy\Nodes\ContentNode([new Texy\Nodes\ParagraphNode(new Texy\Nodes\ContentNode([
		new Texy\Nodes\EmailNode('a@b.cz<i>'),
	]))]));
	Assert::match(
		'<p><a href="mailto:a&#64;b.cz&lt;i&gt;">a&#64;<!-- -->b.cz&lt;i&gt;</a></p>',
		$texy->renderHtml($doc),
	);
});


test('a renderer handler gets the document after the policy', function () {
	$texy = createTexy();
	$texy->htmlOutput->addRenderer(fn(Texy\Nodes\LinkNode $node) => new Texy\Output\Html\Element('a', ['href' => $node->url]));
	Assert::same("<p>click</p>\n", $texy->toHtml('"click":javascript:alert(1)'));
});


test('the transformed document holds only what the policy allows', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy, images: true);
	$texy->urlPolicy->linkPattern = '~https:~A';
	$doc = $texy->parse(
		"\"a\":http://x b [* https://a.cz/x.jpg .(alt) *] www.x.cz\n\n"
		. "[* https://a.cz/a.jpg *]:http://x *** caption\n\n"
		. '"c .[nofollow]":https://x "d .[cls #id]{color: red; cite: javascript:x; lang: cs}"',
	);
	$doc->findFirst(Texy\Nodes\ImageNode::class)->url = 'javascript:x'; // the parser itself refuses such an image
	$texy->transform($doc);

	Assert::same(['https://x'], array_map(fn($link) => $link->url, $doc->find(Texy\Nodes\LinkNode::class)));
	Assert::same(['nofollow' => true], $doc->findFirst(Texy\Nodes\LinkNode::class)->modifier->classes);
	Assert::same([], $doc->find(Texy\Nodes\UrlNode::class));
	Assert::same([null, 'https://a.cz/a.jpg'], array_map(fn($image) => $image->url, $doc->find(Texy\Nodes\ImageNode::class)));
	Assert::type(Texy\Nodes\ImageNode::class, $doc->findFirst(Texy\Nodes\FigureNode::class)->image);

	$modifier = $doc->findFirst(Texy\Nodes\PhraseNode::class)->modifier;
	Assert::same([null, [], [], ['lang' => 'cs']], [$modifier->id, $modifier->classes, $modifier->styles, $modifier->attrs]);
});
