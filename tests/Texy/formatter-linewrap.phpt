<?php declare(strict_types=1);

/**
 * Test: Very long lines.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('long line wrapping', function () {
	$texy = new Texy\Texy;
	Assert::same(
		normalizeNewlines(file_get_contents(__DIR__ . '/expected/formatter-linewrap.html')),
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/formatter-linewrap.texy')),
	);
});


test('long line wrapping (variant)', function () {
	$texy = new Texy\Texy;
	Assert::same(
		normalizeNewlines(file_get_contents(__DIR__ . '/expected/formatter-linewrap-alt.html')),
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/formatter-linewrap-alt.texy')),
	);
});


test('a value of an attribute of raw HTML is not wrapped, inside <pre> neither', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->addRenderer(
		fn(Texy\Nodes\CodeBlockNode $node, Texy\Output\Html\Renderer $renderer, Closure $previous) => new Texy\Output\Html\Raw(
			'<pre><code style="color: #000000"><span style="color: #0000BB">' . htmlspecialchars($node->code) . '</span></code></pre>',
		),
	);
	$html = $texy->toHtml("/--php\n" . str_repeat('$a = 1; ', 12) . "\n\\--");
	Assert::contains('<code style="color: #000000"><span style="color: #0000BB">', $html);

	$html = $texy->toHtml(str_repeat('slovo ', 12) . '<span title="a title of a span long enough to be wrapped somewhere">x</span>');
	Assert::contains('title="a title of a span long enough to be wrapped somewhere"', $html);
});
