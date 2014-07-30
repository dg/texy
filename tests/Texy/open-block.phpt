<?php

/**
 * Test:
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/open-block.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/open-block.texy'))
);
