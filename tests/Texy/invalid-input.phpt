<?php declare(strict_types=1);

/**
 * Test: invalid UTF-8 input fails fast with a clear exception.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('toHtml() rejects invalid UTF-8', function () {
	$texy = new Texy\Texy;
	Assert::exception(
		fn() => $texy->toHtml("valid start \xC3\x28 invalid"),
		Texy\InvalidInputException::class,
		'Input is not valid UTF-8 text.',
	);
});


test('processTypo() rejects invalid UTF-8', function () {
	$texy = new Texy\Texy;
	Assert::exception(
		fn() => $texy->processTypo("\xE2\x28\xA1"),
		Texy\InvalidInputException::class,
		'Input is not valid UTF-8 text.',
	);
});


test('valid UTF-8 including edge characters passes', function () {
	$texy = new Texy\Texy;
	Assert::noError(fn() => $texy->toHtml("čeština, emoji 🙂, \u{FFFD}"));
});


test('what the text causes is a Texy\Exception, what the program does a LogicException', function () {
	$texy = new Texy\Texy;
	$texy->addInlineSyntax('custom/broken', '~(~', fn() => null);
	$e = Assert::exception(fn() => $texy->toHtml('a'), LogicException::class, 'Compilation failed: %a%');
	Assert::false($e instanceof Texy\Exception);

	ini_set('pcre.jit', '0');
	$limit = ini_set('pcre.backtrack_limit', '1000');
	try {
		Assert::exception(fn() => Texy\Regexp::match(str_repeat('x', 40) . '!', '~^(?:x+x+)+$~'), Texy\RegexpException::class);
	} finally {
		ini_set('pcre.backtrack_limit', (string) $limit);
	}
});
