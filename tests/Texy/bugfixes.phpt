<?php

/**
 * Test: bugfixes
 */

require __DIR__ . '/../bootstrap.php';


test(function () { // fixed Offset didn't correspond to the begin of a valid UTF-8 code point
	$texy = new Texy;
	TexyConfigurator::safeMode($texy);
	Assert::truthy($texy->process('"š":xxx://')); // i.e. triggers no error
});

test(function () { // "label":@link
	$texy = new Texy;
	Assert::same("<p><a href=\"&#64;link\">a</a></p>\n", $texy->process('"a":@link'));
});
