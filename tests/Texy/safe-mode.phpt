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
		createTexy()->toHtml('[* a.jpg *]'),
	);
});


test('figures are disabled', function () {
	Assert::match(
		'<p>[* a.jpg *] *** caption</p>',
		createTexy()->toHtml('[* a.jpg *] *** caption'),
	);
});


test('image definitions are disabled', function () {
	Assert::match(
		<<<'XX'
			<p>[<em>x</em>]: a.jpg</p>

			<p>[<em>x</em>] *** caption</p>

			XX,
		createTexy()->toHtml("[*x*]: a.jpg\n\n[*x*] *** caption"),
	);
});


test('images and figures can be kept, their definitions stay disabled', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy, images: true);
	Assert::same(
		"<p>x <img src=\"http://a.cz/a.jpg\" alt=\"\"></p>\n\n<figure><img src=\"http://a.cz/b.jpg\" alt=\"\">\n\t<figcaption>caption</figcaption>\n</figure>\n\n<p><img src=\"x\" alt=\"\">: a.jpg</p>",
		trim($texy->toHtml("x [* http://a.cz/a.jpg *]\n\n[* http://a.cz/b.jpg *] *** caption\n\n[*x*]: a.jpg")),
	);
	Assert::same('<p>[* javascript:x *]</p>', trim($texy->toHtml('[* javascript:x *]')));
});


test('a tab or newline does not hide a forbidden scheme', function () {
	Assert::notContains('<a', createTexy()->toHtml('<a href="jav&Tab;ascript:alert(1)">x</a>'));
	Assert::notContains('<a', createTexy()->toHtml('<a href="jav&#10;ascript:alert(1)">x</a>'));
});


test('the link of a code phrase obeys the URL policy', function () {
	Assert::match(
		'<p><a href="http://a.cz" rel="nofollow"><code>a</code></a></p>',
		createTexy()->toHtml('`a`:http://a.cz'),
	);
	Assert::match(
		'<p><code>a</code></p>',
		createTexy()->toHtml('`a`:[javascript:alert(1)]'),
	);
});
