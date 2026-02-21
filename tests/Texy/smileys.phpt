<?php declare(strict_types=1);

/**
 * Test: Smileys.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$texy = new Texy\Texy;
$texy->allowed['emoticon'] = true;

Assert::matchFile(
	__DIR__ . '/expected/smileys.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/smileys.texy')),
);


$texy->htmlOutputModule->lineWrap = 180;
$texy->emoticonModule->root = 'images/images/';
$texy->emoticonModule->class = 'smiley';
$texy->emoticonModule->icons = [
	':-)' => 'smile.gif',
	':-(' => 'sad.gif',
	'8-O' => 'eek.gif',
];

Assert::matchFile(
	__DIR__ . '/expected/smileys-old.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/smileys.texy')),
);
