<?php

/**
 * Test: bugfixes
 */

require __DIR__ . '/../bootstrap.php';


test(function() { // fixed Offset didn't correspond to the begin of a valid UTF-8 code point
	$texy = new Texy;
	Texy\Configurator::safeMode($texy);
	Assert::truthy($texy->process('"š":xxx://')); // i.e. triggers no error
});
