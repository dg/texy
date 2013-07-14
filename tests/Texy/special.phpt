<?php

/**
 * Test: Special cases.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
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
