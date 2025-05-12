<?php declare(strict_types=1);

/**
 * Test: module configuration changed between parses takes effect on the next parse.
 */

use Tester\Assert;
use Texy\{Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


test('switching the typography locale', function () {
	$texy = new Texy;

	Assert::contains('„q“', $texy->toHtml('"q"')); // cs
	$texy->config->locale = 'en';
	Assert::contains('“q”', $texy->toHtml('"q"'));
});


test('mutating the quote table of the active locale', function () {
	$texy = new Texy;

	Assert::contains('„q“', $texy->toHtml('"q"'));
	[$texy->config->locales['cs']['quotes'][0], $texy->config->locales['cs']['quotes'][1]] = ['«', '»'];
	Assert::contains('«q»', $texy->toHtml('"q"'));
});


test('changing the emoticon table', function () {
	$texy = new Texy;
	$texy->allowedSyntax[Syntax::Emoticon] = true;

	Assert::contains('🙂', $texy->toHtml(':-)'));
	$texy->config->emoticons[':-)'] = '[SMILE]';
	Assert::contains('[SMILE]', $texy->toHtml(':-)'));
});
