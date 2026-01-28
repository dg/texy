<?php declare(strict_types=1);

/**
 * Test: Smileys.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('emoticon with unicode', function () {
	$texy = new Texy\Texy;
	$texy->allowed['emoticon'] = true;

	Assert::matchFile(
		__DIR__ . '/expected/emoticon.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/emoticon.texy')),
	);
});


test('emoticon with custom class', function () {
	$texy = new Texy\Texy;
	$texy->allowed['emoticon'] = true;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->emoticonModule->class = 'smiley';

	Assert::match(
		'<p>I fell <span class="smiley">🙂</span></p>',
		$texy->process('I fell :-)'),
	);
});
