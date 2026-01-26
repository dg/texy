<?php declare(strict_types=1);

/**
 * Test: parse - Block types (div, texysource, html, text).
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function process(string $text): string
{
	$texy = new Texy\Texy;
	return $texy->process($text);
}


test('div block', function () {
	Assert::match(
		<<<'HTML'
			<div>
				<p>Hello <strong>world</strong>!</p>
			</div>
			HTML,
		process(<<<'XX'
			/--div
			Hello **world**!
			\--
			XX),
	);
});


test('div block with class', function () {
	Assert::match(
		<<<'HTML'
			<div class="container">
				<p>Content here</p>
			</div>
			HTML,
		process(<<<'XX'
			/--div .[container]
			Content here
			\--
			XX),
	);
});


test('nested div blocks', function () {
	Assert::match(
		<<<'HTML'
			<div class="outer">
				<div class="inner">
					<p>Nested content</p>
				</div>
			</div>
			HTML,
		process(<<<'XX'
			/--div .[outer]
			/--div .[inner]
			Nested content
			\--
			\--
			XX),
	);
});


test('html block - raw output', function () {
	Assert::match(
		'&lt;custom-element&gt;content&lt;/custom-element&gt;',
		process(<<<'XX'
			/--html
			<custom-element>content</custom-element>
			\--
			XX),
	);
});


test('text block with line breaks', function () {
	Assert::match(
		'Line 1<br>%A%Line 2<br>%A%Line 3',
		process(<<<'XX'
			/--text
			Line 1
			Line 2
			Line 3
			\--
			XX),
	);
});


test('texysource block', function () {
	Assert::match(
		'<pre%A%class="html"><code>%a%</code></pre>',
		process(<<<'XX'
			/--texysource
			**bold** and //italic//
			\--
			XX),
	);
});


test('comment block - no output', function () {
	$html = process(<<<'XX'
		/--comment
		This should not appear
		\--
		XX);
	Assert::same('', trim($html));
});


test('code block with language', function () {
	// Language class goes on <pre>, not <code>
	Assert::match(
		'<pre class="php"><code>echo \'hello\';</code></pre>',
		process(<<<'XX'
			/--code php
			echo 'hello';
			\--
			XX),
	);
});


test('default block (pre)', function () {
	Assert::match(
		'<pre>preformatted</pre>',
		process(<<<'XX'
			/--
			preformatted
			\--
			XX),
	);
});


test('pre block', function () {
	Assert::match(
		'<pre>preformatted text</pre>',
		process(<<<'XX'
			/--pre
			preformatted text
			\--
			XX),
	);
});
