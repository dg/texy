<?php declare(strict_types=1);

/**
 * Test: TexyExtension
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$fn = function (string $text, int $heading = 1, string $locale = 'cs'): string {
	$texy = new Texy\Texy;
	$texy->config->headingTop = $heading;
	$texy->config->locale = $locale;
	return $texy->toHtml($text);
};

$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Texy\Bridges\Latte\TexyExtension($fn));

$template = <<<'XX'
	{texy}
	default
	-----
	{/texy}

	{texy heading: 3}
	"heading"
	-----
	{/texy}

	{texy locale: en, heading: 3}
	"both"
	-----
	{/texy}
	XX;


// Typography: Czech uses „..." and English uses "..."
Assert::match(
	<<<'XX'
		<h1>default</h1>

		<h3>„heading“</h3>

		<h3>“both”</h3>
		XX,
	$latte->renderToString($template),
);


Assert::match(
	'<h2>hello</h2>',
	$latte->renderToString('{="hello\n----"|texy: heading: 2}'),
);


// the content of {texy} reaches a callable without the indentation of the template, static or not
$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Texy\Bridges\Latte\TexyExtension(fn(string $text): string => '[' . $text . ']'));
$html = $latte->renderToString("<div>\n\t{texy}\n\tone\n\t\ttwo\n\t{/texy}\n\t{texy}\n\t\tthree {\$x}\n\t{/texy}\n</div>", ['x' => 'x']);
Assert::contains("[one\n\ttwo", $html);
Assert::contains('[three x]', $html);
