<?php

/**
 * Test: Headings.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/headings1.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/headings1.texy'))
);

$texy->headingModule->generateID = TRUE;

Assert::matchFile(
	__DIR__ . '/expected/headings2.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/headings2.texy'))
);


Assert::count(6, $texy->headingModule->TOC);
Assert::same(1, $texy->headingModule->TOC[0]['level']);
Assert::same('underlined', $texy->headingModule->TOC[0]['type']);
Assert::same('Title', $texy->headingModule->TOC[0]['title']);
Assert::same('h1', $texy->headingModule->TOC[0]['el']->getName());
