<?php declare(strict_types=1);

/**
 * Test: the HTML renderer works without a live Texy instance.
 */

use Tester\Assert;
use Texy\{HtmlPolicy, Modifier, Nodes, Texy, UrlPolicy};
use Texy\Output\Html;

require __DIR__ . '/../bootstrap.php';


test('a hand-assembled renderer renders a parsed document', function () {
	$texy = new Texy;
	$doc = $texy->parse("Heading\n=======\n\nText with **bold**.");
	$texy->transform($doc);

	$renderer = new Html\Renderer($texy->htmlOutput, $texy->htmlPolicy, $texy->urlPolicy);
	Assert::same($texy->createHtmlRenderer()->render($doc), $renderer->render($doc));
});


test('a renderer built from scratch needs no Texy at all', function () {
	$urlPolicy = new UrlPolicy;
	$renderer = new Html\Renderer(new Html\Config, new HtmlPolicy($urlPolicy), $urlPolicy);

	$doc = new Nodes\DocumentNode(new Nodes\ContentNode([
		new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\TextNode('Hello')])),
	]));
	Assert::same("<p>Hello</p>\n", $renderer->render($doc));
});


test('per-document facts come from the document, so one renderer serves different documents', function () {
	$texy = new Texy;
	$renderer = $texy->createHtmlRenderer();

	$plain = $texy->parse('"link":https://example.com');
	$texy->transform($plain);
	$noFollow = $texy->parse("{{texy: nofollow}}\n\n\"link\":https://example.com");
	$texy->transform($noFollow);

	Assert::notContains('nofollow', $renderer->render($plain));
	Assert::contains('rel="nofollow"', $renderer->render($noFollow));
	Assert::notContains('nofollow', $renderer->render($plain)); // no leak from the previous render
});


test('title typography applies to a hand-built AST but is not re-applied to a typographed one', function () {
	$texy = new Texy;

	// parsed document carries meta[typographed] - the already-typographed title must not change
	$doc = $texy->parse('"link .(title "quoted")":https://example.com');
	$texy->transform($doc);
	Assert::contains('title="title „quoted“"', $texy->createHtmlRenderer()->render($doc));

	// hand-built AST has no such mark - the title gets typographed at render time
	$modifier = new Modifier;
	$modifier->title = 'title "quoted"';
	$handMade = new Nodes\DocumentNode(new Nodes\ContentNode([
		new Nodes\ParagraphNode(new Nodes\ContentNode([
			new Nodes\LinkNode('https://example.com', new Nodes\ContentNode([new Nodes\TextNode('link')]), $modifier),
		])),
	]));
	Assert::contains('title="title „quoted“"', $texy->createHtmlRenderer()->render($handMade));
});


test('block/html and block/texysource render from the AST alone', function () {
	$texy = new Texy;
	$doc = $texy->parse("/--html\n<b>raw</b>\n\\--\n\n/--texysource\n**bold**\n\\--");
	$texy->transform($doc);

	$urlPolicy = new UrlPolicy;
	$renderer = new Html\Renderer(new Html\Config, new HtmlPolicy($urlPolicy), $urlPolicy);
	$html = $renderer->render($doc);
	Assert::contains('<b>raw</b>', $html);
	Assert::contains('<code>&lt;p&gt;&lt;strong&gt;bold&lt;/strong&gt;&lt;/p&gt;</code>', $html);
});
