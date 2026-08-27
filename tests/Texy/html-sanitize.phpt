<?php declare(strict_types=1);

/**
 * Test: HtmlSanitizePass - tag whitelist evaluated in the transform phase.
 */

use Tester\Assert;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


test('rejected tags become literal text nodes in the AST', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => true];
	$doc = $texy->parse('a <u>x</u> <b>y</b>');
	$texy->transform($doc);
	$children = $doc->content->children[0]->content->children;

	// <u>...</u> is rejected: open text, content, close text
	Assert::type(Nodes\RawTextNode::class, $children[1]);
	Assert::same('<u>', $children[1]->text);
	Assert::type(Nodes\TextNode::class, $children[2]);
	Assert::same('x', $children[2]->text);
	Assert::type(Nodes\RawTextNode::class, $children[3]);
	Assert::same('</u>', $children[3]->text);

	// <b>...</b> is allowed and stays a paired element
	Assert::type(Nodes\HtmlElementNode::class, $children[5]);
});


test('a tag listed without a key is allowed without attributes', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b', 'a' => ['href']];
	Assert::same(
		'<p><b>x</b> &lt;i&gt;y&lt;/i&gt; <a href="http://x.cz">z</a></p>',
		trim($texy->toHtml('<b class="c">x</b> <i>y</i> <a href="http://x.cz" title="t">z</a>')),
	);

	Texy\Configurator::disableLinks($texy);
	Assert::same(['b'], $texy->htmlPolicy->allowedTags);
});


test('disabling links or images removes their tags listed in any case', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['A' => ['href'], 'B', 'Img' => true, 'EMBED'];
	Texy\Configurator::disableLinks($texy);
	Texy\Configurator::disableImages($texy);
	Assert::same(['B'], $texy->htmlPolicy->allowedTags);
});


test('disabling links or images with all tags allowed keeps the others', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::disableImages($texy);
	Assert::same('<p><b>x</b> &lt;img src="a.png"&gt;</p>', trim($texy->toHtml('<b>x</b> <img src="a.png">')));
});


test('nested rejected elements are sanitized recursively', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['b' => true];
	$doc = $texy->parse('<u><i>x</i></u> a <u><b>y</b></u>');
	$texy->transform($doc);
	$children = $doc->content->children[0]->content->children;

	// everything of <u><i>x</i></u> is text, the tags literal
	foreach ([0, 1, 3, 4] as $i) {
		Assert::type(Nodes\RawTextNode::class, $children[$i]);
	}
	Assert::type(Nodes\TextNode::class, $children[2]);
	// allowed <b> inside rejected <u> survives as element
	Assert::type(Nodes\HtmlElementNode::class, $children[7]);
	Assert::same('b', $children[7]->name);
});


test('rejected pair is escaped consistently in output', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same(
		"<p>&lt;u&gt;text&lt;/u&gt; ok</p>\n",
		$texy->toHtml('<u>text</u> ok'),
	);
});


test('escaped tag text is shown as written, untouched by typography', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$texy->htmlOutput->lineWrap = 0;
	Assert::contains(
		'&lt;font size="3"&gt;x&lt;/font&gt; konec',
		$texy->toHtml('text <font size="3">x</font> konec'),
	);
	Assert::contains(
		'&lt;/nejneobhospodarovavatelnejsimi&gt;',
		$texy->toHtml('text </nejneobhospodarovavatelnejsimi>'),
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
	$result = $texy->toHtml('<a href="javascript:evil()">click</a>');
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
		$texy->htmlPolicy->allowedTags = false;
		Assert::same('<pre>' . htmlspecialchars($tag, ENT_NOQUOTES) . "</pre>\n", $texy->toHtml("/--pre\n$tag\n\\--"), $tag);
	}
});


test('both tags of a rejected pair are shown as written', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same("<p>&lt;SPAN class=c&gt;text&lt;/SPAN &gt; ok</p>\n", $texy->toHtml('<SPAN class=c>text</SPAN > ok'));
});


test('a tag the whitelist leaves invalid is shown as written', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	Assert::same("<p>x &lt;a id=y&gt;t z</p>\n", $texy->toHtml('x <a id=y>t</a> z'));

	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = ['img' => ['alt']];
	Assert::same("<p>x &lt;img src=y alt=z&gt; z</p>\n", $texy->toHtml('x <img src=y alt=z> z'));
});


test('a rejected tag across lines is shown as the parser read it', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = false;
	Assert::same("<blockquote>\n\t<p>a &lt;key foo=bar&gt; z</p>\n</blockquote>\n", $texy->toHtml("> a <key\n> foo=bar> z"));
	Assert::same("<p>x &lt;key foo=bar&gt; y</p>\n", $texy->toHtml("x <key\n  foo=bar> y"));
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
		fn(Texy\Texy $texy) => $texy->htmlPolicy->allowedTags = false,
	];

	foreach ($policies as $policy) {
		foreach ($cases as $source) {
			$texy = new Texy\Texy;
			$policy($texy);
			$html = $texy->toHtml($source);
			Assert::notContains('<script', strtolower($html), $source);
			Assert::notContains('<img', $html, $source);
		}
	}
});


test('a tag without its written form is rebuilt from its attributes', function () {
	$texy = new Texy\Texy;
	$texy->htmlPolicy->allowedTags = false;
	$tag = new Nodes\HtmlTagNode('key', ['a' => 'b', 'c' => true]);
	$doc = new Nodes\DocumentNode(new Nodes\ContentNode([new Nodes\ParagraphNode(new Nodes\ContentNode([$tag]))]));
	Assert::same('&lt;key a="b" c&gt;', $texy->renderHtml($doc));
});
