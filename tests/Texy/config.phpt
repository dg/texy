<?php declare(strict_types=1);

/**
 * Test: Config and TexyConfig.
 */

use Tester\Assert;
use Texy\{Config, InvalidArgumentException, Texy, TexyConfig};

require __DIR__ . '/../bootstrap.php';


test('the configuration object cannot be replaced', function () {
	$texy = new Texy;
	Assert::exception(fn() => $texy->config = new TexyConfig, Error::class, 'Cannot modify readonly property %a%');
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
		fn() => $texy->applyTypography('text'),
		InvalidArgumentException::class,
		"Config::\$locale 'xx' is not a key of Config::\$locales.",
	);
});


test('quotes of a locale are a list of four', function () {
	$texy = new Texy;
	$texy->config->locales['cs'] = ['«', '»'];
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"Config::\$locales['cs'] must be a list of four quotes.",
	);
});


test('a locale added to the table is accepted', function () {
	$texy = new Texy;
	$texy->config->locales['xx'] = ['[', ']', '<', '>'];
	$texy->config->locale = 'xx';
	Assert::same('<p>[q]</p>', trim($texy->toHtml('"q"')));
});


test('values changed between parse() and transform() are checked again', function () {
	$texy = new Texy;
	$document = $texy->parse('text');
	$texy->config->headingTop = 9;
	Assert::exception(fn() => $texy->transform($document), InvalidArgumentException::class, 'Config::$headingTop must be 1..6, 9 given.');
});


test('an invalid table of underlined headings is refused', function () {
	$texy = new Texy;
	$texy->config->headingLevels['#'] = 0;
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"TexyConfig::\$headingLevels['#'] must be 1..6, 0 given.",
	);

	$texy = new Texy;
	$texy->config->headingLevels['~'] = 1;
	Assert::exception(
		fn() => $texy->parse('text'),
		InvalidArgumentException::class,
		"TexyConfig::\$headingLevels has a key '~', only # * = - underline a heading.",
	);
});


test('an underline character missing from the table is refused when it is read', function () {
	$texy = new Texy;
	unset($texy->config->headingLevels['=']);
	Assert::exception(
		fn() => $texy->parse("Title\n==="),
		InvalidArgumentException::class,
		"TexyConfig::\$headingLevels has no level for '='.",
	);
});


test('a directive changes the configuration before the heading pass of the same document', function () {
	$texy = new Texy;
	$texy->config->addDirective('deep', function () use ($texy) { $texy->config->headingTop = 3; });
	Assert::contains('<h3>', $texy->toHtml("{{deep}}\n\nTitle\n====="));
});


test('two instances of a facade do not share definitions read from their documents', function () {
	$texy = new Texy;
	$first = $texy->toHtml("[x]: https://one.cz\n\n\"a\":[x]");
	$second = $texy->toHtml('"a":[x]');
	Assert::contains('https://one.cz', $first);
	Assert::notContains('https://one.cz', $second);
});


test('objects of configuration can be copied for an independent variant', function () {
	$original = new Config;
	$copy = clone $original;
	$copy->locale = 'en';
	Assert::same('cs', $original->locale);
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
