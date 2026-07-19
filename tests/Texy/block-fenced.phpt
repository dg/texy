<?php declare(strict_types=1);

/**
 * Test: Fenced code blocks ```language
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('fenced block with language', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre class=\"php\"><code>\$x = 1;</code></pre>\n",
		$texy->process("```php\n\$x = 1;\n```"),
	);
});


test('fenced block without language', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre><code>plain code</code></pre>\n",
		$texy->process("```\nplain code\n```"),
	);
});


test('content is verbatim - no Texy parsing', function () {
	$texy = new Texy\Texy;
	$html = $texy->process("```\n**not bold** \"not\":[a-link] [ref]\n```");
	Assert::same(
		"<pre><code>**not bold** \"not\":[a-link] [ref]</code></pre>\n",
		$html,
	);
});


test('unclosed fence runs to the end of input', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre><code>first\nsecond</code></pre>\n",
		$texy->process("```\nfirst\nsecond"),
	);
});


test('longer fence can embed shorter backtick runs', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre class=\"texy\"><code>```\ninner\n```</code></pre>\n",
		$texy->process("````texy\n```\ninner\n```\n````"),
	);
});


test('info string: first word is the language', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre class=\"js\"><code>x</code></pre>\n",
		$texy->process("```js runnable example\nx\n```"),
	);
});


test('fence inside /-- block stays verbatim', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre class=\"md\"><code>```\ninner\n```</code></pre>\n",
		$texy->process("/--code md\n```\ninner\n```\n\\--"),
	);
});


test('surrounding paragraphs are unaffected', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<p>before</p>\n\n<pre class=\"c\"><code>code</code></pre>\n\n<p>after</p>\n",
		$texy->process("before\n\n```c\ncode\n```\n\nafter"),
	);
});


test('can be disabled', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::CodeFenced] = false;
	$html = $texy->process("```\ncode\n```");
	Assert::notContains('<pre', $html);
});


test('roundtrip: Markdown generator emits what the parser reads', function () {
	$texy = new Texy\Texy;
	$doc = $texy->parse("```php\n\$x = 1;\n```");
	$md = new Texy\Output\Markdown\Renderer;
	$markdown = $md->render($doc);
	Assert::same("```php\n\$x = 1;\n```\n", $markdown);

	// and back
	$texy2 = new Texy\Texy;
	Assert::same(
		"<pre class=\"php\"><code>\$x = 1;</code></pre>\n",
		$texy2->process($markdown),
	);
});


test('line starting with fence chars plus info is content (GFM: closing fence has no info)', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre><code>code\n```x</code></pre>\n",
		$texy->process("```\ncode\n```x\n```"),
	);
});


test('intentional blank lines at the start of content stay', function () {
	$texy = new Texy\Texy;
	Assert::same(
		"<pre><code>\ncode</code></pre>\n",
		$texy->process("```\n\ncode\n```"),
	);
});
