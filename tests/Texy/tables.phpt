<?php

/**
 * Test: Tables
 */

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/tables.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/tables.texy'))
);
