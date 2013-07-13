<?php

/**
 * Test: Special cases.
 */

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->linkModule->root = 'xxx/';
$texy->imageModule->root = '../images/';
$texy->imageModule->linkedRoot = '../images/big/';
$texy->imageModule->leftClass = 'left';
$texy->htmlOutputModule->lineWrap = 180;
$texy->allowed['longwords'] = FALSE;
$texy->typographyModule->locale = 'en';

Assert::matchFile(
	__DIR__ . '/expected/special.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/special.texy'))
);
