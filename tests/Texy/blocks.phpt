<?php

/**
 * Test: Blocks
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->linkModule->root = 'xxx/';
$texy->imageModule->root = '../images/';
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/blocks1.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/blocks1.texy'))
);

Assert::matchFile(
	__DIR__ . '/expected/blocks2.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/blocks2.texy'))
);
