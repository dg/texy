<?php declare(strict_types=1);

/**
 * Test: parse - Typography with protect mechanism.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('ellipsis conversion', function () {
	$texy = new Texy\Texy;

	$html = $texy->process('Wait for it...');
	Assert::match('%A%…%A%', $html); // ellipsis character
});


test('dash conversion', function () {
	$texy = new Texy\Texy;

	// En dash between numbers
	$html = $texy->process('Numbers 10-20 here.');
	Assert::match('%A%10–20%A%', $html);
});


test('em dash conversion', function () {
	$texy = new Texy\Texy;

	$html = $texy->process('Text --- more text.');
	Assert::match('%A%—%A%', $html); // em dash
});


test('no typography in code blocks', function () {
	$texy = new Texy\Texy;

	// Code content should not be modified by typography
	// Quotes inside code should remain as ASCII quotes
	$html = $texy->process(<<<'XX'
		/--code php
		echo "hello";
		\--
		XX);
	Assert::match('%A%"hello"%A%', $html);
});


test('no typography in inline code', function () {
	$texy = new Texy\Texy;

	// Inline code should not be modified - quotes remain as-is
	$html = $texy->process('Use `echo "test";` command.');
	Assert::match('%A%"test"%A%', $html);
});


test('typography with nested phrases', function () {
	$texy = new Texy\Texy;

	// Typography should work on text between phrase tags
	$html = $texy->process('Text **with bold -- and dash** here.');
	Assert::match('%A%–%A%', $html); // en dash
});


test('arrows conversion', function () {
	$texy = new Texy\Texy;

	$html = $texy->process('Go --> that way.');
	Assert::match('%A%→%A%', $html); // right arrow
});


test('copyright and trademark', function () {
	$texy = new Texy\Texy;

	$html = $texy->process('Product(TM) by Company(C).');
	Assert::match('%A%™%A%', $html);
	Assert::match('%A%©%A%', $html);
});
