<?php

/**
 * Test: indentation.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->htmlOutputModule->baseIndent = 1;

Assert::matchFile(
	__DIR__ . '/expected/indent.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/indent.texy'))
);
