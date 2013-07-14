<?php

/**
 * Test: link & images.
 */

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy;
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
$texy->allowedClasses = array('#nofollow');
$texy->allowedStyles = false;
Assert::matchFile(
	__DIR__ . '/expected/links-images3.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/links-images.texy'))
);
