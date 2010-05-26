<?php

/**
 * Test: link & images.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->imageModule->root = '../images/';
	$texy->imageModule->linkedRoot = '../images/big/';
	$texy->imageModule->leftClass = 'left';
	$texy->htmlOutputModule->lineWrap = 180;
	return $texy;
}

$texy = createTexy();
Assert::matchFile(
	__DIR__ . '/expected/links-images1.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/links-images.texy'))
);

$texy = createTexy();
Texy\Configurator::safeMode($texy);
$texy->allowedTags['a'][] = 'rel';
Assert::matchFile(
	__DIR__ . '/expected/links-images2.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/links-images.texy'))
);

$texy = createTexy();
$texy->allowedClasses = ['#nofollow'];
$texy->allowedStyles = FALSE;
Assert::matchFile(
	__DIR__ . '/expected/links-images3.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/links-images.texy'))
);

Assert::error(function () {
	$texy = createTexy();
	$texy->process('[* texy.jpg | texy-over.jpg *]');
}, E_USER_WARNING, 'Syntax [* image | over | linked *] is deprecated, [* texy.jpg | texy-over.jpg *] is partially ignored.');
