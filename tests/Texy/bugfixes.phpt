<?php declare(strict_types=1);

/**
 * Test: bugfixes
 */

use Tester\Assert;
use Texy\Configurator;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('fixed Offset didn\'t correspond to the begin of a valid UTF-8 code point', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::truthy($texy->process('"š":xxx://')); // i.e. triggers no error
});

test('"label":@link', function () {
	$texy = new Texy;
	Assert::same("<p><a href=\"&#64;link\">a</a></p>\n", $texy->process('"a":@link'));
});

test('allowed XSS for URLs #31', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::same("<p>&lt;a href=„jAvascrip­t://“&gt;click</p>\n", $texy->process('<a href="jAvascript://">click</a>'));
});

test('allowed XSS for URLs #31', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::same("<p>a</p>\n", $texy->process('"a":[javaScript:alert()]'));
});

test('allowed XSS for URLs #34', function () {
	$texy = new Texy;
	Configurator::safeMode($texy);
	Assert::same("<p>&lt;a href=\" javascript:\"&gt;click</p>\n", $texy->process('<a href=" javascript:">click</a>'));
});
