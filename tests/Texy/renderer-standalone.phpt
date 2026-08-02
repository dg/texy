<?php declare(strict_types=1);

/**
 * Test: the HTML renderer works without a live Texy instance.
 */

use Tester\Assert;
use Texy\HtmlPolicy;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Output\Html;
use Texy\Texy;
use Texy\UrlPolicy;

require __DIR__ . '/../bootstrap.php';


test('a hand-assembled renderer renders a parsed document', function () {
	$texy = new Texy;
	$doc = $texy->parse("Heading\n=======\n\nText with **bold**.");

	$renderer = new Html\Renderer($texy->htmlOutput, $texy->htmlPolicy, $texy->urlPolicy);
	Assert::same($texy->renderHtml($doc), $renderer->render($doc));
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
	$noFollow = $texy->parse("{{texy: nofollow}}\n\n\"link\":https://example.com");

	Assert::notContains('nofollow', $renderer->render($plain));
	Assert::contains('rel="nofollow"', $renderer->render($noFollow));
	Assert::notContains('nofollow', $renderer->render($plain)); // no leak from the previous render
});


test('title typography applies to a hand-built AST but is not re-applied to a typographed one', function () {
	$texy = new Texy;

	// parsed document carries meta[typographed] - the already-typographed title must not change
	$doc = $texy->parse('"link .(title "quoted")":https://example.com');
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


test('rendering block/html without a parse context factory fails loudly', function () {
	$texy = new Texy;
	$doc = $texy->parse("/--html\n<b>raw</b>\n\\--");

	$urlPolicy = new UrlPolicy;
	$renderer = new Html\Renderer(new Html\Config, new HtmlPolicy($urlPolicy), $urlPolicy);
	Assert::exception(
		fn() => $renderer->render($doc),
		LogicException::class,
		'%a%requires a parse context%a%',
	);
});
