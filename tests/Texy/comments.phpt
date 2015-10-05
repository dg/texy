<?php

/**
 * Test: HTML comments.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;

Assert::matchFile(
	__DIR__ . '/expected/comments.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/comments.texy'))
);
