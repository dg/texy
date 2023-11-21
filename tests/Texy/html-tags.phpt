<?php

/**
 * Test: HTML tags.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy()
{
	$texy = new Texy\Texy;
	$texy->linkModule->root = 'xxx/';
	$texy->htmlOutputModule->lineWrap = 180;
	return $texy;
}


$texy = createTexy();
Assert::matchFile(
	__DIR__ . '/expected/html-tags1a.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/html-tags1.texy')),
);

Texy\Configurator::safeMode($texy);
Assert::matchFile(
	__DIR__ . '/expected/html-tags1b.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/html-tags1.texy')),
);

$texy->allowedTags = $texy::NONE;
Assert::matchFile(
	__DIR__ . '/expected/html-tags1c.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/html-tags1.texy')),
);

Assert::matchFile(
	__DIR__ . '/expected/html-tags2.html',
	createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags2.texy')),
);

Assert::matchFile(
	__DIR__ . '/expected/html-tags3.html',
	createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags3.texy')),
);

Assert::matchFile(
	__DIR__ . '/expected/html-tags4.html',
	createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags4.texy')),
);

Assert::matchFile(
	__DIR__ . '/expected/html-tags5.html',
	createTexy()->process(file_get_contents(__DIR__ . '/sources/html-tags5.texy')),
);
