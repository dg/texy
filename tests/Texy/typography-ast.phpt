<?php declare(strict_types=1);

/**
 * Test: typography and hyphenation as an AST transform.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function convert(string $text): string
{
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	return $texy->toHtml($text);
}


test('basic typography', function () {
	Assert::same("<p>Rekl „ahoj“ a sel…</p>\n", convert('Rekl "ahoj" a sel...'));
	Assert::same("<p>Vazi 5\u{A0}kg a stoji 10\u{A0}EUR</p>\n", convert('Vazi 5 kg a stoji 10 EUR'));
	Assert::same("<p>datum 23.\u{A0}1.\u{A0}1978 je datum</p>\n", convert('datum 23. 1. 1978 je datum'));
	Assert::same("<p>rozmer je 10\u{A0}×\u{A0}20 cm</p>\n", convert('rozmer je 10 x 20 cm'));
	Assert::same("<p>text © a ™ a ±</p>\n", convert('text (c) a (TM) a +-'));
	Assert::same("<p>Text\u{A0}— more text. Go → that way.</p>\n", convert('Text --- more text. Go --> that way.'));
});


test('quotes pair across phrase boundaries', function () {
	Assert::same(
		"<p>řekl „ahoj <strong>světe</strong>!“ a šel</p>\n",
		convert('řekl "ahoj **světe**!" a šel'),
	);
});


test('the content of an unknown inline node gets typography', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('outside "quoted" ...');
	$doc->findFirst(Texy\Nodes\ParagraphNode::class)->content->children[] = new class (new Texy\Nodes\ContentNode(
		[new Texy\Nodes\TextNode(' and inside "quoted" ...')],
	)) extends Texy\Nodes\InlineNode {
		public function __construct(
			public Texy\Nodes\ContentNode $content,
		) {
		}


		public function &getChildren(): Generator
		{
			yield $this->content;
		}
	};

	Assert::same("<p>outside „quoted“ … and inside „quoted“ …</p>\n", $texy->createHtmlRenderer()->render($texy->transform($doc)));
});


test('code phrase content is protected from typography', function () {
	Assert::same(
		"<p>napiš <code>\"quotes\" -- ...</code> takto</p>\n",
		convert('napiš `"quotes" -- ...` takto'),
	);
});


test('code block content is protected from typography', function () {
	Assert::same(
		"<pre class=\"php\"><code>echo \"hello\" -- x...;</code></pre>\n",
		convert("/--code php\necho \"hello\" -- x...;\n\\--"),
	);
});


test('HTML code and preformatted text keep their text verbatim', function () {
	Assert::same("<p>a <code>\"q\" -- b</code> „c“</p>\n", convert('a <code>"q" -- b</code> "c"'));
	Assert::same("<pre>\na b  \"c\"\n</pre>\n", convert("<pre>\na b  \"c\"\n</pre>"));
});


test('long words get soft hyphens', function () {
	Assert::contains("\u{AD}", convert('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));
});


test('typography can be disabled', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->config->typography = false;
	Assert::same("<p>Rekl \"ahoj\" a sel...</p>\n", $texy->toHtml('Rekl "ahoj" a sel...'));
});


test('hyphenation can be disabled', function () {
	$texy = new Texy\Texy;
	$texy->config->hyphenation = false;
	Assert::notContains("\u{AD}", $texy->toHtml('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));
});


test('modifier title gets typography', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p title=\"říká „ahoj“\">text</p>\n",
		$texy->toHtml('text .(říká "ahoj")'),
	);
});


test('disabled typography leaves titles and alt alone', function () {
	$texy = new Texy\Texy;
	$texy->config->typography = false;
	Assert::same(
		"<p title=\"s &quot;uvozovkami&quot;\">text -- ok</p>\n",
		$texy->toHtml('text -- ok .(s "uvozovkami")'),
	);

	$texy = new Texy\Texy;
	$texy->config->typography = false;
	$html = $texy->toHtml('[* obr.png .(popisek "v uvozovkach") *]');
	Assert::contains('alt="popisek &quot;v uvozovkach&quot;"', $html);
});


test('image alt gets typography', function () {
	$texy = new Texy\Texy;
	$html = $texy->toHtml('[* obr.png .(popisek "v uvozovkach") *]');
	Assert::contains("alt=\"popisek „v\u{A0}uvozovkach“\"", $html);
});


test('every output format inherits typography from the AST', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse('Rekl "ahoj" a sel...');
	$texy->transform($doc);
	Assert::same("Rekl „ahoj“ a sel…\n", (new Texy\Output\Text\Renderer)->render($doc));
});


test('entities are decoded before typography', function () {
	// &#160; must act as nbsp, not as a 6-character word for hyphenation
	Assert::same(
		"<p>slovo\u{A0}dlouhes\u{AD}lovokterehyphe\u{AD}nacevidi neco</p>\n",
		convert('slovo&#160;dlouheslovokterehyphenacevidi neco'),
	);
});


test('block-level passthrough tag splits the typographic segment', function () {
	// quotes must not pair across a block tag
	Assert::same(
		"text \"prvni\n<div>blok</div>\n druha\" konec",
		convert('text "prvni <div>blok</div> druha" konec'),
	);
});


test('notexy content is protected from typography and hyphenation', function () {
	Assert::same(
		"<p>text \"a\" --b &lt;c&gt; text</p>\n",
		convert("text ''\"a\" --b <c>'' text"),
	);
	Assert::same(
		"<h1>-w | --watch &lt;path&gt;</h1>\n",
		convert("''-w | --watch <path>''\n--------"),
	);
	Assert::match(
		'%A%<td>"x"</td>%A%',
		convert("| ''\"x\"'' |"),
	);
	Assert::same(
		"<p>Dlouheslovohodnedlouhatezkorozdelitelne</p>\n",
		convert("''Dlouheslovohodnedlouhatezkorozdelitelne''"),
	);
});


test('notexy content does not pair quotes with the text around it', function () {
	Assert::same(
		"<p>„ahoj x\"y světe“</p>\n",
		convert("\"ahoj ''x\"y'' světe\""),
	);
});


test('phrase modifier title gets typography', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p><strong title=\"s „uvozovkami“\">tucne</strong></p>\n",
		$texy->toHtml('**tucne .(s "uvozovkami")**'),
	);
});
