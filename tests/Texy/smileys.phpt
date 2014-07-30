<?php

/**
 * Test: Smileys.
 */

use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->htmlOutputModule->lineWrap = 180;
$texy->emoticonModule->root  = 'images/images/';
$texy->emoticonModule->class  = 'smiley';
$texy->allowed['emoticon'] = TRUE;

Assert::matchFile(
	__DIR__ . '/expected/smileys.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/smileys.texy'))
);
