<?php declare(strict_types=1);

/**
 * Test: the HTML renderer does not trust a tree built by code
 */

use Tester\Assert;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


function renderSafe(Texy\Node ...$inline): string
{
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$texy->htmlOutput->lineWrap = 0;
	return $texy->renderHtml(new Nodes\DocumentNode(new Nodes\ContentNode([
		new Nodes\ParagraphNode(new Nodes\ContentNode($inline)),
	])));
}


test('an autolink obeys the URL policy', function () {
	Assert::same("<p>javascript:alert(1)</p>\n", renderSafe(new Nodes\UrlNode('javascript:alert(1)')));
	Assert::same(
		"<p><a href=\"https://x.cz\" rel=\"nofollow\">https://x.cz</a></p>\n",
		renderSafe(new Nodes\UrlNode('https://x.cz')),
	);
});


test('an e-mail obeys the URL policy', function () {
	$texy = new Texy\Texy;
	$texy->urlPolicy->linkPattern = '~https?:~A';
	Assert::same("<p>a&#64;<!-- -->b.cz</p>\n", $texy->toHtml('a@b.cz'));
	Assert::same("<p>a&#64;<!-- -->b.cz</p>\n", $texy->renderHtml(new Nodes\DocumentNode(new Nodes\ContentNode([
		new Nodes\ParagraphNode(new Nodes\ContentNode([new Nodes\EmailNode('a@b.cz')])),
	]))));
});
