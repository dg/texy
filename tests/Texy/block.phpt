<?php declare(strict_types=1);

/**
 * Test: Blocks
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nested div blocks', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/block.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/block.texy')),
	);
});


test('block type variants', function () {
	$texy = new Texy\Texy;
	$texy->allowedSyntax[Texy\Syntax::DelimitedTexySource] = true;
	$texy->htmlOutput->linkBaseUrl = 'xxx/';
	$texy->htmlOutput->imageBaseUrl = '../images/';
	$texy->htmlOutput->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/block-types.html',
		$texy->toHtml(file_get_contents(__DIR__ . '/sources/block-types.texy')),
	);
});


test('a block of many lines is parsed', function () {
	$texy = new Texy\Texy;
	foreach (["/--code\n%s\\--", "/--code\n%s", "/--div\n%s\\--"] as $block) {
		Assert::noError(fn() => $texy->toHtml(sprintf($block, str_repeat("line\n", 40_000))));
	}
});


test('blocks nested deeper than the limit are parsed', function () {
	$texy = new Texy\Texy;
	$html = $texy->toHtml(str_repeat("/--div\n", 2_000) . "text\n" . str_repeat("\\--\n", 2_000));
	Assert::same(Texy\Parsing\ParseContext::MaxDepth, substr_count($html, '<div>'));
});
