<?php declare(strict_types=1);

/**
 * Test: Texy::normalize() canonical form.
 */

use Tester\Assert;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


test('line endings are converted to \n', function () {
	Assert::same("a\nb\nc", Texy::normalize("a\r\nb\rc"));
});


test('leading byte order mark is removed', function () {
	Assert::same('text', Texy::normalize("\u{FEFF}text"));
	Assert::same('text', Texy::normalize("\n\u{FEFF}\ntext")); // surfaces after the blank-line trim
	Assert::same("te\u{FEFF}xt", Texy::normalize("te\u{FEFF}xt")); // only the leading one
});


test('control characters are removed, tabs stay', function () {
	Assert::same('ab', Texy::normalize("a\x00\x08\x0B\x1Fb"));
	Assert::same("a\tb", Texy::normalize("a\tb"));
});


test('soft hyphens are preserved', function () {
	Assert::same("wo\u{AD}rd", Texy::normalize("wo\u{AD}rd"));
});


test('lines are right-trimmed', function () {
	Assert::same("a\nb", Texy::normalize("a  \t\nb\t"));
});


test('blank edge lines are dropped', function () {
	Assert::same('text', Texy::normalize("\n\n  \ntext\n \n\n"));
});


test('idempotence', function () {
	$dirty = "\u{FEFF}\r\n first\u{AD} line  \r\nsecond\tline \x07\n\n";
	$clean = Texy::normalize($dirty);
	Assert::same($clean, Texy::normalize($clean));
});


test('invalid UTF-8 is rejected', function () {
	Assert::exception(
		fn() => Texy::normalize("valid \xC3\x28 invalid"),
		\Texy\InvalidArgumentException::class,
		'Input is not valid UTF-8 text.',
	);
});
