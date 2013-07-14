<?php

/**
 * Test: bugfixes
 */

use Tester\Assert;
use Texy\Configurator;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test(function () { // fixed Offset didn't correspond to the begin of a valid UTF-8 code point
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::truthy($texy->process('"š":xxx://')); // i.e. triggers no error
});

test(function () { // "label":@link
	$texy = new Texy;
	Assert::same("<p><a href=\"&#64;link\">a</a></p>\n", $texy->process('"a":@link'));
});

test(function () { // allowed XSS for URLs #31
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::same("<p>&lt;a href=„jAvascrip­t://“&gt;click</p>\n", $texy->process('<a href="jAvascript://">click</a>'));
});

test(function () { // allowed XSS for URLs #31
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::same("<p>a</p>\n", $texy->process('"a":[javaScript:alert()]'));
});

test(function () { // allowed XSS for URLs #34
	$texy = new Texy;
	TexyConfigurator::safeMode($texy);
	Assert::same("<p>&lt;a href=\" javascript:\"&gt;click</p>\n", $texy->process('<a href=" javascript:">click</a>'));
});
