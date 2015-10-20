<?php

/**
 * Test: HTML5
 */

use Tester\Assert;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test(function () {
	$texy = new Texy;
	Assert::same("<div data-test=\"hello\"></div>\n", $texy->process('<div data-test=hello>'));
});

test(function () {
	$texy = new Texy;
	Assert::same("<p data-attr=\"val\">hello</p>\n", $texy->process('hello .{data-attr: val}'));
});

test(function () {
	$texy = new Texy;
	Assert::same("<div data-test=\"hello\"></div>\n", $texy->process('<div data-test=hello>'));
});
