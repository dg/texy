<?php

/**
 * Test: Paragraphs.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->linkModule->root = 'xxx/';
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/paragraphs.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/paragraphs.texy'))
);
