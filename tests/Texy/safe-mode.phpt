<?php declare(strict_types=1);

/**
 * Test: Configurator::safeMode()
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy(): Texy\Texy
{
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	return $texy;
}


test('images are disabled', function () {
	Assert::match(
		'<p>[* a.jpg *]</p>',
		createTexy()->process('[* a.jpg *]'),
	);
});


test('figures are disabled', function () {
	Assert::match(
		'<p>[* a.jpg *] *** caption</p>',
		createTexy()->process('[* a.jpg *] *** caption'),
	);
});


test('image definitions are disabled', function () {
	Assert::match(
		<<<'XX'
			<p>[<em>x</em>]: a.jpg</p>

			<p>[<em>x</em>] *** caption</p>

			XX,
		createTexy()->process("[*x*]: a.jpg\n\n[*x*] *** caption"),
	);
});


test('a tab or newline does not hide a forbidden scheme', function () {
	Assert::match('<p>a</p>', createTexy()->process("\"a\":[java\tscript:alert(1)]"));
	Assert::match('<p>a</p>', createTexy()->process("[a|java\tscript:alert(1)]"));
	Assert::notContains('<a', createTexy()->process('<a href="jav&Tab;ascript:alert(1)">x</a>'));
	Assert::notContains('<a', createTexy()->process('<a href="jav&#10;ascript:alert(1)">x</a>'));
});


test('the link of a code phrase obeys the URL policy', function () {
	Assert::match(
		'<p><a href="http://a.cz" rel="nofollow"><code>a</code></a></p>',
		createTexy()->process('`a`:http://a.cz'),
	);
	Assert::match(
		'<p><code>a</code></p>',
		createTexy()->process('`a`:[javascript:alert(1)]'),
	);
});
