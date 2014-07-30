<?php

/**
 * Test: HTML comments.
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;

Assert::matchFile(
	__DIR__ . '/expected/comments.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/comments.texy'))
);
