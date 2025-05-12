<?php declare(strict_types=1);

/**
 * Test: Config.
 */

use Tester\Assert;
use Texy\{Config, InvalidArgumentException, Texy};

require __DIR__ . '/../bootstrap.php';


test('the configuration object cannot be replaced', function () {
	$texy = new Texy;
	Assert::exception(fn() => $texy->config = new Config, Error::class, 'Cannot modify readonly property %a%');
});


foreach ([0, 7, -1] as $top) {
	test("headingTop $top is refused when the text is parsed", function () use ($top) {
		$texy = new Texy;
		$texy->config->headingTop = $top;
		Assert::exception(fn() => $texy->parse('text'), InvalidArgumentException::class, "Config::\$headingTop must be 1..6, $top given.");
	});
}


test('a locale missing from the table is refused', function () {
	$texy = new Texy;
	$texy->config->locale = 'xx';
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"Config::\$locale 'xx' is not a key of Config::\$locales.",
	);
	Assert::exception(
		fn() => $texy->processTypo('text'),
		InvalidArgumentException::class,
		"Config::\$locale 'xx' is not a key of Config::\$locales.",
	);
});


test('quotes of a locale are a list of four', function () {
	$texy = new Texy;
	$texy->config->locales['cs'] = ['quotes' => ['«', '»']];
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"Config::\$locales['cs']['quotes'] must be a list of four quotes.",
	);

	$texy->config->locales['cs'] = ['«', '»', '‹', '›'];
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"Config::\$locales['cs']['quotes'] must be a list of four quotes.",
	);
});


test('a locale added to the table is accepted', function () {
	$texy = new Texy;
	$texy->config->locales['xx'] = ['quotes' => ['[', ']', '<', '>']];
	$texy->config->locale = 'xx';
	Assert::same('<p>[q]</p>', trim($texy->toHtml('"q"')));
});


test('values changed between parse() and transform() are checked again', function () {
	$texy = new Texy;
	$document = $texy->parse('text');
	$texy->config->headingTop = 9;
	Assert::exception(fn() => $texy->transform($document), InvalidArgumentException::class, 'Config::$headingTop must be 1..6, 9 given.');
});


test('definitions read from one document do not leak into the next one', function () {
	$texy = new Texy;
	$first = $texy->toHtml("[x]: https://one.cz\n\n\"a\":[x]");
	$second = $texy->toHtml('"a":[x]');
	Assert::contains('https://one.cz', $first);
	Assert::notContains('https://one.cz', $second);
});


test('typography and hyphenation are switched by the configuration', function () {
	$texy = new Texy;
	$texy->config->typography = false;
	Assert::same('"q"...', trim(strip_tags($texy->toHtml('"q"...'))));

	$texy->config->typography = true;
	$texy->config->hyphenation = false;
	$word = str_repeat('slabika', 10);
	Assert::same($word, trim(strip_tags($texy->toHtml($word))));

	$texy->config->hyphenation = true;
	Assert::notSame($word, trim(strip_tags($texy->toHtml($word))));
});
