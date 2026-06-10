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


test('a unit after a range is bound to the number', function () {
	Assert::same("<p>stála 1500–2000\u{A0}Kč a vážila 10–12\u{A0}kg</p>\n", convert('stála 1500-2000 Kč a vážila 10-12 kg'));
});


test('the space after a one-letter word is fixed by the words of the locale', function () {
	Assert::same("<p>Byl jsem v\u{A0}Praze a I\u{A0}tam</p>\n", convert('Byl jsem v Praze a I tam'));

	$texy = new Texy\Texy;
	$texy->config->locale = 'sk';
	Assert::same("<p>Bol som v\u{A0}Prahe a\u{A0}tam</p>\n", $texy->toHtml('Bol som v Prahe a tam'));
	$texy->config->locale = 'pl';
	Assert::same("<p>Byłem w\u{A0}Pradze i\u{A0}tam</p>\n", $texy->toHtml('Byłem w Pradze i tam'));
	$texy->config->locale = 'en';
	Assert::same("<p>I went to a shop in a town.</p>\n", $texy->toHtml('I went to a shop in a town.'));

	$texy->config->locales['hr'] = ['quotes' => $texy->config->locales['cs']['quotes'], 'oneLetterWords' => 'iu'];
	$texy->config->locale = 'hr';
	Assert::same("<p>Bio sam u\u{A0}Zagrebu i\u{A0}tamo</p>\n", $texy->toHtml('Bio sam u Zagrebu i tamo'));
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

	Assert::same("<p>outside „quoted“ … and inside „quoted“ …</p>\n", $texy->renderHtml($texy->transform($doc)));
});


test('code phrase content is protected from typography', function () {
	Assert::same(
		"<p>napiš <code>\"quotes\" -- ...</code> takto</p>\n",
		convert('napiš `"quotes" -- ...` takto'),
	);
});


test('code block content is protected from typography', function () {
	Assert::same(
		"<pre class=\"language-php\"><code>echo \"hello\" -- x...;</code></pre>\n",
		convert("/--code php\necho \"hello\" -- x...;\n\\--"),
	);
});


test('HTML code and preformatted text keep their text verbatim', function () {
	Assert::same("<p>a <code>\"q\" -- b</code> „c“</p>\n", convert('a <code>"q" -- b</code> "c"'));
	Assert::same("<pre>\na\tb  \"c\"\n</pre>\n", convert("<pre>\na\tb  \"c\"\n</pre>"));
});


test('long words get soft hyphens, by Czech syllables in Czech and Slovak only', function () {
	Assert::contains("\u{AD}", convert('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));

	$texy = new Texy\Texy;
	$texy->config->locale = 'sk';
	Assert::contains("\u{AD}", $texy->toHtml('Dlouheslovohodnedlouhatezkorozdelitelne slovo'));
	$texy->config->locale = 'en';
	Assert::same("<p>antidisestablishmentarianism</p>\n", $texy->toHtml('antidisestablishmentarianism'));
	Assert::same('antidisestablishmentarianism', $texy->applyTypography('antidisestablishmentarianism'));
});


test('a link displaying its own address is protected like an autolink', function () {
	$url = 'https://example.com/nejneobhospodarovavatelnejsimi';
	$link = fn(string $text, string $url) => new Texy\Nodes\LinkNode($url, Texy\Nodes\ContentNode::fromText($text));
	$texy = new Texy\Texy;
	$doc = new Texy\Nodes\DocumentNode(new Texy\Nodes\ContentNode([new Texy\Nodes\ParagraphNode(new Texy\Nodes\ContentNode([
		$link($url, $url),
		$link('jana@nejneobhospodarovavatelnejsimi.cz', 'mailto:jana@nejneobhospodarovavatelnejsimi.cz'),
		$link('www.nejneobhospodarovavatelnejsimi.cz', 'http://www.nejneobhospodarovavatelnejsimi.cz'),
	]))]));
	$texy->transform($doc);
	Assert::notContains("\u{AD}", $texy->renderHtml($doc));

	Assert::contains("\u{AD}", convert('"nejneobhospodarovavatelnejsimi":https://example.com'));
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
