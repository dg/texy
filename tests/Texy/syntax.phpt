<?php

/**
 * Test: Complete syntax.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->imageModule->root = '../images/';
$texy->imageModule->leftClass = 'left';
$texy->allowed['phrase/ins'] = TRUE;
$texy->allowed['phrase/del'] = TRUE;
$texy->allowed['phrase/sup'] = TRUE;
$texy->allowed['phrase/sub'] = TRUE;
$texy->allowed['phrase/cite'] = TRUE;
$texy->typographyModule->locale = 'en';
$texy->horizLineModule->classes['*'] = 'hidden';

$time = microtime(TRUE);
Assert::matchFile(
	__DIR__ . '/expected/syntax.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/syntax.texy'))
);

Assert::matchFile(
	__DIR__ . '/expected/syntax.txt',
	$texy->toText()
);
echo $time - microtime(TRUE);
