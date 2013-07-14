<?php

/**
 * Test: Very long lines.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::same(
	str_replace("\r", '', file_get_contents(__DIR__ . '/expected/long-line1.html')),
	$texy->process(file_get_contents(__DIR__ . '/sources/long-line1.texy'))
);

Assert::same(
	str_replace("\r", '', file_get_contents(__DIR__ . '/expected/long-line2.html')),
	$texy->process(file_get_contents(__DIR__ . '/sources/long-line2.texy'))
);
