<?php declare(strict_types=1);

/**
 * Test: module configuration changed between parses takes effect on the next parse.
 */

use Tester\Assert;
use Texy\{Syntax, Texy};

require __DIR__ . '/../bootstrap.php';


test('switching the typography locale', function () {
	$texy = new Texy;

	Assert::contains("\u{201E}q\u{201C}", $texy->toHtml('"q"')); // cs
	$texy->config->locale = 'en';
	Assert::contains("\u{201C}q\u{201D}", $texy->toHtml('"q"'));
});


test('mutating the quote table of the active locale', function () {
	$texy = new Texy;

	Assert::contains("\u{201E}q\u{201C}", $texy->toHtml('"q"'));
	[$texy->config->locales['cs'][0], $texy->config->locales['cs'][1]] = ['«', '»'];
	Assert::contains('«q»', $texy->toHtml('"q"'));
});


test('changing the emoticon table', function () {
	$texy = new Texy;
	$texy->allowedSyntax[Syntax::Emoticon] = true;

	Assert::contains('🙂', $texy->toHtml(':-)'));
	$texy->config->emoticons[':-)'] = '[SMILE]';
	Assert::contains('[SMILE]', $texy->toHtml(':-)'));
});


test('removing a list bullet', function () {
	$texy = new Texy;

	Assert::contains('<ul>', $texy->toHtml("* one\n* two"));
	unset($texy->config->listBullets['*']);
	Assert::notContains('<ul>', $texy->toHtml("* one\n* two"));
});
