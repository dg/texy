<?php

/**
 * Test: Non-UTF-8 encoding.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->encoding = 'windows-1250';

Assert::matchFile(
	__DIR__ . '/expected/windows-1250.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/windows-1250.texy'))
);
