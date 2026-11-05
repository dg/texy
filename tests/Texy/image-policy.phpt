<?php declare(strict_types=1);

/**
 * Test: Image URL rejected by UrlPolicy
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createTexy(): Texy\Texy
{
	$texy = new Texy\Texy;
	$texy->urlPolicy->imagePattern = '~https?:~A';
	return $texy;
}


test('allowed image is rendered', function () {
	Assert::match(
		'<p>a <img src="http://x.cz/a.jpg" alt=""> b</p>',
		createTexy()->process('a [* http://x.cz/a.jpg *] b'),
	);
});


test('rejected image stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *] b</p>',
		createTexy()->process('a [* javascript:alert(1) *] b'),
	);
});


test('rejected image linked to itself stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *]:: b</p>',
		createTexy()->process('a [* javascript:alert(1) *]:: b'),
	);
});


test('rejected image with a link stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *]:<a href="http://x.cz">http://x.cz</a> b</p>',
		createTexy()->process('a [* javascript:alert(1) *]:http://x.cz b'),
	);
});


test('rejected figure stays as source text', function () {
	Assert::match(
		'<p>[* javascript:alert(1) *] *** caption</p>',
		createTexy()->process('[* javascript:alert(1) *] *** caption'),
	);
});


test('reference to a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p>a <img src="images/x" alt=""> b</p>',
		createTexy()->process("[*x*]: javascript:alert(1)\n\na [*x*] b"),
	);
});


test('link to a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p><a href="images/x">text</a></p>',
		createTexy()->process("[*x*]: javascript:alert(1)\n\n\"text\":[*x*]"),
	);
});


test('image linked to itself by a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p>a <a href="images/x"><img src="images/x" alt=""></a> b</p>',
		createTexy()->process("[*x*]: javascript:alert(1)\n\na [*x*]:: b"),
	);
});


test('figure with a definition with rejected URL acts as undefined', function () {
	Assert::match(
		<<<'XX'
			<div class="figure"><img src="images/x" alt="">
				<p>caption</p>
			</div>

			XX,
		createTexy()->process("[*x*]: javascript:alert(1)\n\n[*x*] *** caption"),
	);
});


test('definition with rejected URL is not printed back', function () {
	$texy = createTexy();
	Assert::same(
		"text\n",
		$texy->renderTexy($texy->parse("[*x*]: javascript:alert(1)\n\ntext")),
	);
});


test('definition with rejected URL does not overwrite a user-defined one', function () {
	$texy = createTexy();
	$texy->imageModule->addDefinition('x', 'logo.png');
	Assert::match(
		'<p>a <img src="images/logo.png" alt=""> b</p>',
		$texy->process("[*x*]: javascript:alert(1)\n\na [*x*] b"),
	);
});
