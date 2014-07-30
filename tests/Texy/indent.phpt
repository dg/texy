<?php

/**
 * Test: indentation.
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->htmlOutputModule->baseIndent = 1;

Assert::matchFile(
	__DIR__ . '/expected/indent.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/indent.texy'))
);
