<?php

/**
 * Test: Complete syntax.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->tabWidth = 0;
$texy->imageModule->root = '../images/';
$texy->imageModule->leftClass = 'left';
$texy->allowed['phrase/ins'] = true;
$texy->allowed['phrase/del'] = true;
$texy->allowed['phrase/sup'] = true;
$texy->allowed['phrase/sub'] = true;
$texy->allowed['phrase/cite'] = true;
$texy->typographyModule->locale = 'en';
$texy->horizLineModule->classes['*'] = 'hidden';

Assert::matchFile(
	__DIR__ . '/expected/syntax.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/syntax.texy'))
);

Assert::matchFile(
	__DIR__ . '/expected/syntax.txt',
	$texy->toText()
);
