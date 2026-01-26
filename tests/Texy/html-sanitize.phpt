<?php declare(strict_types=1);

/**
 * Test: HtmlSanitizePass - tag whitelist evaluated in the transform phase.
 */

use Tester\Assert;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


test('rejected tags become text nodes in the AST', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => Texy\Texy::ALL];
	$doc = $texy->parse('a <u>x</u> <b>y</b>');
	$texy->transform($doc);
	$children = $doc->content->children[0]->content->children;

	// <u>...</u> is rejected: open text, content, close text
	Assert::type(Nodes\TextNode::class, $children[1]);
	Assert::same('<u>', $children[1]->text);
	Assert::type(Nodes\TextNode::class, $children[2]);
	Assert::same('x', $children[2]->text);
	Assert::type(Nodes\TextNode::class, $children[3]);
	Assert::same('</u>', $children[3]->text);

	// <b>...</b> is allowed and stays a paired element
	Assert::type(Nodes\HtmlElementNode::class, $children[5]);
});


test('nested rejected elements are sanitized recursively', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => Texy\Texy::ALL];
	$doc = $texy->parse('<u><i>x</i></u> a <u><b>y</b></u>');
	$texy->transform($doc);
	$children = $doc->content->children[0]->content->children;

	// everything of <u><i>x</i></u> is text
	foreach ([0, 1, 2, 3, 4] as $i) {
		Assert::type(Nodes\TextNode::class, $children[$i]);
	}
	// allowed <b> inside rejected <u> survives as element
	Assert::type(Nodes\HtmlElementNode::class, $children[7]);
	Assert::same('b', $children[7]->name);
});


test('rejected pair is escaped consistently in output', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same(
		"<p>&lt;u&gt;text&lt;/u&gt; ok</p>\n",
		$texy->process('<u>text</u> ok'),
	);
});


test('escaped tag text participates in typography', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$texy->htmlOutput->lineWrap = 0;
	Assert::contains(
		'&lt;font size=„3“&gt;',
		$texy->process('text <font size="3">x</font> konec'),
	);
});


test('other output formats render rejected tags as text', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$doc = $texy->parse('<u>klik</u> text');
	$texy->transform($doc);
	Assert::same("<u>klik</u> text\n", (new Texy\Output\Text\Renderer)->render($doc));
});


test('dangerous URL scheme is neutralized in transform phase', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$result = $texy->process('<a href="javascript:evil()">click</a>');
	Assert::notContains('<a href', $result); // no active link
	Assert::contains('&lt;a href', $result); // escaped as text
});


test('a rejected tag is shown as written', function () {
	$tags = [
		'<a href=x>',
		"<a href='x'>",
		'<A  HREF=x>',
		'<br/>',
		'<input disabled>',
		"<foo  bar='1'  baz=\"2\"  />",
		'<key a=1 a=2>',
		'</key >',
	];

	foreach ($tags as $tag) {
		$texy = new Texy\Texy;
		$texy->htmlPolicy->allowedTags = Texy\Texy::NONE;
		Assert::same('<pre>' . htmlspecialchars($tag, ENT_NOQUOTES) . "</pre>\n", $texy->process("/--pre\n$tag\n\\--"), $tag);
	}
});


test('both tags of a rejected pair are shown as written', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same("<p>&lt;SPAN class=c&gt;text&lt;/SPAN &gt; ok</p>\n", $texy->process('<SPAN class=c>text</SPAN > ok'));
});


test('a tag the whitelist leaves invalid is shown as written', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same("<p>x &lt;a id=y&gt;t z</p>\n", $texy->process('x <a id=y>t</a> z'));

	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['img' => ['alt']];
	Assert::same("<p>x &lt;img src=y alt=z&gt; z</p>\n", $texy->process('x <img src=y alt=z> z'));
});


test('a rejected tag across lines is shown as the parser read it', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = Texy\Texy::NONE;
	Assert::same("<blockquote>\n\t<p>a &lt;key foo=bar&gt; z</p>\n</blockquote>\n", $texy->process("> a <key\n> foo=bar> z"));
	Assert::same("<p>x &lt;key foo=bar&gt; y</p>\n", $texy->process("x <key\n  foo=bar> y"));
});


test('nothing inside a rejected tag becomes active', function () {
	$cases = [
		'x <key a="<script>alert(1)</script>"> y',
		'x <key a="</p><script>"> y',
		'x <SCRIPT  src=x>alert(1)</SCRIPT > y',
		'x <img  onerror=alert(1) src=x> y',
	];
	$policies = [
		fn(Texy\Texy $texy) => Texy\Configurator::safeMode($texy),
		fn(Texy\Texy $texy) => $texy->htmlPolicy->allowedTags = Texy\Texy::NONE,
	];

	foreach ($policies as $policy) {
		foreach ($cases as $source) {
			$texy = new Texy\Texy;
			$policy($texy);
			$html = $texy->process($source);
			Assert::notContains('<script', strtolower($html), $source);
			Assert::notContains('<img', $html, $source);
		}
	}
});
