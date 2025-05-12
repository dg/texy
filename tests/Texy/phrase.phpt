<?php declare(strict_types=1);

/**
 * Test: parse - Phrases (inline formatting).
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function process(string $text): string
{
	$texy = new Texy\Texy;
	return $texy->toHtml($text);
}


test('strong phrase', function () {
	Assert::match(
		'<p><strong>bold</strong></p>',
		process('**bold**'),
	);
});


test('emphasis phrase', function () {
	Assert::match(
		'<p><em>italic</em></p>',
		process('//italic//'),
	);
});


test('code phrase', function () {
	Assert::match(
		'<p><code>code</code></p>',
		process('`code`'),
	);
});


test('code phrase with a link', function () {
	$link = '<p><a href="http://a.cz"><code>foo</code></a></p>';
	Assert::match($link, process('`foo`:http://a.cz'));
	Assert::match($link, process('`foo`:[http://a.cz]'));
	Assert::match($link, process("`foo`:[ref]\n\n[ref]: http://a.cz"));
	Assert::match('<p>See <a href="http://a.cz"><code>foo</code></a>.</p>', process('See `foo`:http://a.cz.'));
});


test('code phrase with a link keeps its modifier and its literal content', function () {
	Assert::match(
		'<p><a href="http://a.cz"><code class="bar">foo</code></a></p>',
		process('`foo .[bar]`:http://a.cz'),
	);
	Assert::match(
		'<p><a href="http://a.cz"><code>**a** &lt;b&gt;</code></a></p>',
		process('`**a** <b>`:http://a.cz'),
	);
});


test('whitespace before the modifier of a code phrase is not its content', function () {
	Assert::match(
		'<p><code class="bar">foo</code></p>',
		process("`foo\t .[bar]`"),
	);
	Assert::match(
		'<p><a href="http://a.cz"><code class="bar">foo</code></a></p>',
		process("`foo\t .[bar]`:http://a.cz"),
	);
});


test('a phrase loses its link when links are disabled', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::disableLinks($texy);
	Assert::match(
		'<p><code>foo</code> and <strong>bar</strong></p>',
		$texy->toHtml('`foo`:http://a.cz and **bar**:http://b.cz'),
	);
});


test('strong+emphasis phrase', function () {
	Assert::match(
		'<p><strong><em>bold italic</em></strong></p>',
		process('***bold italic***'),
	);
});


test('nested phrases', function () {
	Assert::match(
		'<p><strong>bold and <em>italic</em> text</strong></p>',
		process('**bold and //italic// text**'),
	);
});


test('phrase with modifier', function () {
	Assert::match(
		'<p><strong class="highlight">bold</strong></p>',
		process('**bold .[highlight]**'),
	);
});


test('acronym with explanation', function () {
	Assert::match(
		'<p><abbr title="North Atlantic Treaty Organisation">NATO</abbr></p>',
		process('NATO((North Atlantic Treaty Organisation))'),
	);
});


test('the tag of a phrase follows what it means, whatever the notation', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->phraseTags[Texy\Nodes\PhraseType::Emphasis->value] = 'i';
	$texy->htmlOutput->phraseTags[Texy\Nodes\PhraseType::Abbreviation->value] = 'dfn';
	Assert::same(
		'<p><i>a</i> <i>b</i> <dfn title="t">NATO</dfn> <dfn title="u">et al.</dfn></p>',
		trim($texy->toHtml('*a* //b// NATO((t)) "et al."((u))')),
	);

	$phrases = $texy->parse('*a* //b//')->find(Texy\Nodes\PhraseNode::class);
	Assert::same(
		[[Texy\Nodes\PhraseType::Emphasis, Texy\Syntax::EmphasisSingleAsterisk], [Texy\Nodes\PhraseType::Emphasis, Texy\Syntax::Emphasis]],
		array_map(fn($phrase) => [$phrase->type, $phrase->syntax], $phrases),
	);
});


test('quoted acronym', function () {
	Assert::match(
		'<p><abbr title="and others">et al.</abbr></p>',
		process('"et al."((and others))'),
	);
	Assert::match(
		'<p><abbr title="t"><strong>x</strong> y</abbr></p>',
		process('"**x** y"((t))'),
	);
});
