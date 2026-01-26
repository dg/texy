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
		createTexy()->toHtml('a [* http://x.cz/a.jpg *] b'),
	);
});


test('rejected image stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *] b</p>',
		createTexy()->toHtml('a [* javascript:alert(1) *] b'),
	);
});


test('rejected image linked to itself stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *]:: b</p>',
		createTexy()->toHtml('a [* javascript:alert(1) *]:: b'),
	);
});


test('rejected image with a link stays as source text', function () {
	Assert::match(
		'<p>a [* javascript:alert(1) *]:<a href="http://x.cz">http://x.cz</a> b</p>',
		createTexy()->toHtml('a [* javascript:alert(1) *]:http://x.cz b'),
	);
});


test('rejected figure stays as source text', function () {
	Assert::match(
		'<p>[* javascript:alert(1) *] *** caption</p>',
		createTexy()->toHtml('[* javascript:alert(1) *] *** caption'),
	);
});


test('reference to a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p>a <img src="x" alt=""> b</p>',
		createTexy()->toHtml("[*x*]: javascript:alert(1)\n\na [*x*] b"),
	);
});


test('link to a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p><a href="x">text</a></p>',
		createTexy()->toHtml("[*x*]: javascript:alert(1)\n\n\"text\":[*x*]"),
	);
});


test('image linked to itself by a definition with rejected URL acts as undefined', function () {
	Assert::match(
		'<p>a <a href="x"><img src="x" alt=""></a> b</p>',
		createTexy()->toHtml("[*x*]: javascript:alert(1)\n\na [*x*]:: b"),
	);
});


test('figure with a definition with rejected URL acts as undefined', function () {
	Assert::match(
		<<<'XX'
			<figure><img src="x" alt="">
				<figcaption>caption</figcaption>
			</figure>

			XX,
		createTexy()->toHtml("[*x*]: javascript:alert(1)\n\n[*x*] *** caption"),
	);
});


test('definition with rejected URL does not overwrite a user-defined one', function () {
	$texy = createTexy();
	$texy->config->addImageDefinition('x', 'logo.png');
	Assert::match(
		'<p>a <img src="logo.png" alt=""> b</p>',
		$texy->toHtml("[*x*]: javascript:alert(1)\n\na [*x*] b"),
	);
});


test('image node with rejected URL renders as its alternative text', function () {
	$texy = createTexy();
	$texy->config->addImageDefinition('x', 'javascript:alert(1)', alt: 'Logo <b>');
	Assert::match(
		'<p>a Logo &lt;b&gt; b</p>',
		$texy->toHtml('a [*x*] b'),
	);
});


test('linked image node with rejected URL keeps the link', function () {
	$texy = createTexy();
	$texy->config->addImageDefinition('x', 'javascript:alert(1)', alt: 'Logo');
	Assert::match(
		'<p>a <a href="http://x.cz">Logo</a> b</p>',
		$texy->toHtml('a [*x*]:http://x.cz b'),
	);
});


test('image node without URL renders as its alternative text', function () {
	$texy = createTexy();
	$texy->registerLinePattern(fn() => new Texy\Nodes\ImageNode(alt: 'Logo'), '~@@~', 'myimage');
	Assert::match(
		'<p>a Logo b</p>',
		$texy->toHtml('a @@ b'),
	);
});


test('renderer returns an element for an image node with rejected URL', function () {
	$texy = createTexy();
	$texy->htmlOutput->addRenderer(
		function (Texy\Nodes\ImageNode $node, Texy\Output\Html\Renderer $renderer, ?Closure $previous) {
			$el = $previous($node);
			$el->attrs['data-custom'] = 'yes';
			return $el;
		},
	);
	$texy->config->addImageDefinition('x', 'javascript:alert(1)', alt: 'Logo');
	Assert::match(
		'<p>a Logo b</p>',
		$texy->toHtml('a [*x*] b'),
	);
});
