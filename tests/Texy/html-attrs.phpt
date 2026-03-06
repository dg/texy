<?php declare(strict_types=1);

/**
 * Test: attributes of passthrough HTML tags are filtered by the allowedTags whitelist.
 */

use Tester\Assert;
use Texy\{Configurator, Node, NodeHelpers, Nodes, Texy};

require __DIR__ . '/../bootstrap.php';


test('safe mode strips event handlers and styles from hand-written tags', function () {
	$cases = [
		'<a href="https://example.com" onclick="alert(1)">klik</a>' => 'onclick',
		'<b onmouseover="alert(1)">tuk</b>' => 'onmouseover',
		'<b style="position:fixed;top:0">tuk</b>' => 'style',
		'<abbr title="x" onload="alert(1)">zkr</abbr>' => 'onload',
	];

	foreach ($cases as $source => $forbidden) {
		$texy = new Texy;
		Configurator::safeMode($texy);
		Assert::notContains($forbidden, $texy->process($source), $source);
	}
});


test('the tag itself and its allowed attributes survive', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	$html = $texy->process('<a href="https://example.com" title="popis" onclick="alert(1)">klik</a>');

	Assert::contains('<a href="https://example.com"', $html);
	Assert::contains('title="popis"', $html);
	Assert::notContains('onclick', $html);
});


test('a custom whitelist names both the tags and their attributes', function () {
	$texy = new Texy;
	$texy->htmlPolicy->allowedTags = ['a' => ['href'], 'b' => []];

	$html = $texy->process('<a href="/x" title="t">odkaz</a> <b class="c">tuk</b>');

	Assert::contains('href="/x"', $html);
	Assert::notContains('title=', $html);   // 'a' allows href only
	Assert::contains('<b>', $html);          // 'b' allows no attribute at all
	Assert::notContains('class=', $html);
});


test('Texy::All on a tag lets every attribute through', function () {
	$texy = new Texy;
	$texy->htmlPolicy->allowedTags = ['b' => Texy::All];

	Assert::contains('onmouseover', $texy->process('<b onmouseover="x">tuk</b>'));
});


test('filtering also applies to paired elements, not just lone tags', function () {
	// the pairing pass turns these into HtmlElementNode, a different branch
	$texy = new Texy;
	Configurator::safeMode($texy);
	$html = $texy->process("<b onmouseover=\"alert(1)\">first</b>\n\nsecond");

	Assert::notContains('onmouseover', $html);
	Assert::contains('<b>first</b>', $html);
});


test('the AST already carries filtered attributes, so every output format is covered', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	$doc = $texy->parse('<b onmouseover="alert(1)">tuk</b>');
	$texy->transform($doc);

	$found = [];
	$filter = fn(Node $node) => $node instanceof Nodes\HtmlElementNode || $node instanceof Nodes\HtmlTagNode;
	foreach (NodeHelpers::find($doc, $filter) as $node) {
		$found[] = array_keys($node->attributes);
	}

	Assert::notContains('onmouseover', array_merge(...$found ?: [[]]));
});
