<?php declare(strict_types=1);

/**
 * Test: invalid UTF-8 input fails fast with a clear exception.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('process() rejects invalid UTF-8', function () {
	$texy = new Texy\Texy;
	Assert::exception(
		fn() => $texy->process("valid start \xC3\x28 invalid"),
		Texy\InvalidArgumentException::class,
		'Input is not valid UTF-8 text.',
	);
});


test('processTypo() rejects invalid UTF-8', function () {
	$texy = new Texy\Texy;
	Assert::exception(
		fn() => $texy->processTypo("\xE2\x28\xA1"),
		Texy\InvalidArgumentException::class,
		'Input is not valid UTF-8 text.',
	);
});


test('valid UTF-8 including edge characters passes', function () {
	$texy = new Texy\Texy;
	Assert::noError(fn() => $texy->process("čeština, emoji 🙂, \u{FFFD}"));
});
