<?php

/**
 * Test: Paragraphs.
 */

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->linkModule->root = 'xxx/';
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/paragraphs.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/paragraphs.texy'))
);
