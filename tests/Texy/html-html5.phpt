<?php declare(strict_types=1);

/**
 * Test: HTML5
 */

use Tester\Assert;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('data-* attributes', function () {
	$texy = new Texy;
	Assert::same("<div data-test=\"hello\"></div>\n", $texy->process('<div data-test=hello>'));
});


test('data-attr modifier', function () {
	$texy = new Texy;
	Assert::same("<p data-attr=\"val\">hello</p>\n", $texy->process('hello .{data-attr: val}'));
	Assert::same("<div data-test=\"hello\"></div>\n", $texy->process('<div data-test=hello>'));
});


test('aria-* attributes', function () {
	$texy = new Texy;
	Assert::same("<div aria-foo=\"hello\"></div>\n", $texy->process('<div aria-foo=hello>'));
});
